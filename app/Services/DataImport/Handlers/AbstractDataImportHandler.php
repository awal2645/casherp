<?php

namespace App\Services\DataImport\Handlers;

use App\Business;
use App\BusinessDataImport;
use App\BusinessDataImportRow;
use App\BusinessLocation;
use App\Services\DataImport\Contracts\DataImportHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

abstract class AbstractDataImportHandler implements DataImportHandler
{
    public function prepare(array $row, Business $business, ?BusinessLocation $location = null): array
    {
        $data = [];
        foreach ($row as $key => $value) {
            $key = Str::snake(trim((string) $key));
            if ($key === '') {
                continue;
            }
            $data[$key] = is_string($value) ? trim($value) : $value;
            if ($data[$key] === '') {
                $data[$key] = null;
            }
        }

        $data = $this->normalize($data, $business, $location);
        $validator = Validator::make($data, $this->rules($business));
        $validator->after(function ($validator) use ($data, $business, $location) {
            foreach ($this->referenceErrors($data, $business, $location) as $field => $messages) {
                foreach (Arr::wrap($messages) as $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        });

        $errors = $validator->errors()->toArray();
        ksort($data);

        return [
            'data' => $data,
            'errors' => $errors,
            'fingerprint' => hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
        ];
    }

    protected function normalize(array $data, Business $business, ?BusinessLocation $location): array
    {
        return $data;
    }

    protected function referenceErrors(array $data, Business $business, ?BusinessLocation $location): array
    {
        return [];
    }

    abstract protected function rules(Business $business): array;

    protected function result(Model $model, bool $created, array $previous = []): array
    {
        return [
            'status' => $created ? 'created' : 'updated',
            'target_type' => get_class($model),
            'target_id' => $model->getKey(),
            'previous_values' => [
                'before' => $created ? null : $previous,
                'after' => $created
                    ? ['_updated_at' => optional($model->fresh()->updated_at)->format('Y-m-d H:i:s.u')]
                    : $this->snapshot($model->fresh(), array_keys($previous)),
            ],
        ];
    }

    protected function duplicateResult(Model $model): array
    {
        return [
            'status' => 'skipped',
            'target_type' => get_class($model),
            'target_id' => $model->getKey(),
            'previous_values' => [],
        ];
    }

    protected function handleExisting(BusinessDataImport $import, Model $model, array $attributes): array
    {
        if ($import->duplicate_strategy === 'skip') {
            return $this->duplicateResult($model);
        }
        if ($import->duplicate_strategy !== 'update') {
            throw new RuntimeException('A matching record already exists. Select Skip or Update duplicates, then retry.');
        }

        $previous = $this->snapshot($model, array_keys($attributes));
        $model->fill($this->withoutNulls($attributes));
        $model->save();

        return $this->result($model, false, $previous);
    }

    protected function rollbackModel(BusinessDataImport $import, BusinessDataImportRow $row, string $modelClass): void
    {
        if ($row->status === 'skipped' || ! $row->target_id || $row->target_type !== $modelClass) {
            return;
        }

        $query = $modelClass::query()->whereKey($row->target_id);
        $this->scopeRollbackQuery($query, $import);
        $model = $query->first();
        if (! $model) {
            return;
        }

        $rollback = (array) $row->previous_values;
        $after = (array) ($rollback['after'] ?? []);
        if (array_key_exists('_updated_at', $after)) {
            $updatedAt = optional($model->updated_at)->format('Y-m-d H:i:s.u');
            if ($updatedAt !== $after['_updated_at']) {
                throw new RuntimeException('Rollback stopped because this record was changed after the import.');
            }
        } elseif ($after && $this->snapshot($model, array_keys($after)) !== $after) {
            throw new RuntimeException('Rollback stopped because imported fields were changed after the import.');
        }

        if ($row->status === 'created') {
            $this->assertCanDeleteCreated($model);
            $model->delete();
            return;
        }

        if ($row->status === 'updated') {
            $model->fill((array) ($rollback['before'] ?? []));
            $model->save();
        }
    }

    protected function scopeRollbackQuery($query, BusinessDataImport $import): void
    {
        $query->where('business_id', $import->business_id);
    }

    /**
     * A created record may have gained children without changing its own
     * timestamp. Deleting it could then cascade-delete live operational data.
     * Handlers declare every known reference that must block rollback.
     */
    protected function assertCanDeleteCreated(Model $model): void
    {
    }

    protected function assertNoDependencies(Model $model, array $references): void
    {
        foreach ($references as $reference) {
            [$table, $column] = $reference;
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, $column)
                && DB::table($table)->where($column, $model->getKey())->exists()) {
                throw new RuntimeException(
                    'Rollback stopped because this record is now used by operational data in '.$table.'.'
                );
            }
        }
    }

    /**
     * JSON round-tripping gives dates, booleans, decimals and JSON casts the
     * same scalar representation used by the row audit payload. This avoids
     * false rollback conflicts caused only by Eloquent cast object types.
     */
    protected function snapshot(Model $model, array $keys): array
    {
        return json_decode(
            json_encode($model->only($keys), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            true
        ) ?: [];
    }

    protected function withoutNulls(array $values): array
    {
        return array_filter($values, static fn ($value) => $value !== null);
    }

    protected function toBoolean($value)
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'yes', 'y', 'true', 'active', 'enabled'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'no', 'n', 'false', 'inactive', 'disabled'], true)) {
            return false;
        }

        // Preserve an unrecognised value so Laravel's boolean validator can
        // report it instead of silently treating bad input as an empty cell.
        return $value;
    }

    protected function resolveLocation(Business $business, ?BusinessLocation $selected, ?string $name): ?BusinessLocation
    {
        if ($name !== null) {
            return BusinessLocation::where('business_id', $business->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
                ->whereNull('deleted_at')
                ->first();
        }

        return $selected;
    }
}
