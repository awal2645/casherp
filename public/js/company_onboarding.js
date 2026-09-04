(function (window, document, $) {
    'use strict';

    var profiles = window.CASHERP_ONBOARDING_PROFILES || {};
    var oldAnswers = window.CASHERP_ONBOARDING_OLD_ANSWERS || {};
    var form = document.querySelector('[data-onboarding-form]');

    if (!form) {
        return;
    }

    var selector = form.querySelector('[data-industry-selector]');
    var profilePanel = form.querySelector('[data-industry-profile]');
    var questionsPanel = form.querySelector('[data-industry-questions]');
    var draftKey = form.getAttribute('data-draft-key');
    var restoredDraft = false;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function profileForSelection() {
        return selector && profiles[String(selector.value)]
            ? profiles[String(selector.value)]
            : null;
    }

    function fieldsNamed(name) {
        return Array.prototype.filter.call(form.elements, function (field) {
            return field.name === name;
        });
    }

    function answerValue(question) {
        if (Object.prototype.hasOwnProperty.call(oldAnswers, question.key)) {
            return oldAnswers[question.key];
        }

        if ((question.type || 'boolean') === 'multiselect') {
            return Array.prototype.map.call(
                form.querySelectorAll('[name="onboarding[' + question.key + '][]"]:checked'),
                function (field) { return field.value; }
            );
        }

        var input = form.querySelector('[name="onboarding[' + question.key + ']"]');
        if (input) {
            return input.type === 'checkbox' ? input.checked : input.value;
        }

        return question.default;
    }

    function optionSelected(value, selected) {
        if (Array.isArray(selected)) {
            return selected.map(String).indexOf(String(value)) !== -1;
        }
        return String(selected == null ? '' : selected) === String(value);
    }

    function renderQuestion(question) {
        var type = question.type || 'boolean';
        var key = escapeHtml(question.key);
        var value = answerValue(question);
        var required = question.required ? ' required' : '';
        var control = '';

        if (type === 'select') {
            control = '<select id="onboarding_' + key + '" class="form-control" name="onboarding[' + key + ']"' + required + '>' +
                '<option value="">Select an option</option>' +
                (question.options || []).map(function (option) {
                    return '<option value="' + escapeHtml(option.value) + '"' +
                        (optionSelected(option.value, value) ? ' selected' : '') + '>' +
                        escapeHtml(option.label) + '</option>';
                }).join('') +
                '</select>';
        } else if (type === 'multiselect') {
            var dependsOn = question.depends_on ? escapeHtml(question.depends_on) : '';
            var showAllFor = (question.show_all_for || []).map(String).join(',');
            control = '<div class="row tw-mt-2" role="group" aria-labelledby="onboarding_' + key + '_label"' +
                (dependsOn ? ' data-onboarding-depends-on="' + dependsOn + '" data-show-all-for="' + escapeHtml(showAllFor) + '"' : '') + '>' +
                (question.options || []).map(function (option, index) {
                    var optionId = 'onboarding_' + key + '_' + index;
                    return '<div class="col-md-6 tw-mb-2"' +
                        (option.parent ? ' data-onboarding-option-parent="' + escapeHtml(option.parent) + '"' : '') + '>' +
                        '<label class="tw-flex tw-gap-2 tw-items-start tw-font-normal" for="' + optionId + '">' +
                            '<input id="' + optionId + '" type="checkbox" name="onboarding[' + key + '][]" value="' + escapeHtml(option.value) + '"' +
                                (optionSelected(option.value, value) ? ' checked' : '') + '>' +
                            '<span>' + escapeHtml(option.label) + '</span>' +
                        '</label>' +
                    '</div>';
                }).join('') +
                '</div>';
        } else {
            var checked = value === true || String(value) === '1' ? ' checked' : '';
            control = '<input type="hidden" name="onboarding[' + key + ']" value="0">' +
                '<label class="tw-flex tw-gap-2 tw-items-start" for="onboarding_' + key + '">' +
                    '<input id="onboarding_' + key + '" type="checkbox" name="onboarding[' + key + ']" value="1"' + checked + '>' +
                    '<span>' + escapeHtml(question.label) + '</span>' +
                '</label>';
        }

        var label = type === 'boolean'
            ? ''
            : '<label id="onboarding_' + key + '_label" for="onboarding_' + key + '">' +
                escapeHtml(question.label) + (question.required ? ':*' : ':') + '</label>';

        return '<div class="' + (type === 'multiselect' ? 'col-md-12' : 'col-md-6') + '">' +
            '<div class="form-group tw-p-3 tw-rounded-lg tw-border tw-border-gray-200">' +
                label + control +
                '<span class="help-block tw-mb-0">' + escapeHtml(question.help || '') + '</span>' +
            '</div>' +
        '</div>';
    }

    function applyQuestionDependencies() {
        if (!questionsPanel) {
            return;
        }

        Array.prototype.forEach.call(
            questionsPanel.querySelectorAll('[data-onboarding-depends-on]'),
            function (group) {
                var parentKey = group.getAttribute('data-onboarding-depends-on');
                var parent = form.querySelector('[name="onboarding[' + parentKey + ']"]');
                var parentValue = parent ? String(parent.value) : '';
                var showAll = (group.getAttribute('data-show-all-for') || '')
                    .split(',')
                    .filter(Boolean)
                    .indexOf(parentValue) !== -1;

                Array.prototype.forEach.call(
                    group.querySelectorAll('[data-onboarding-option-parent]'),
                    function (wrapper) {
                        var visible = showAll
                            || wrapper.getAttribute('data-onboarding-option-parent') === parentValue;
                        var input = wrapper.querySelector('input');
                        wrapper.classList.toggle('tw-hidden', !visible);
                        if (input) {
                            input.disabled = !visible;
                            if (!visible) {
                                input.checked = false;
                            }
                        }
                    }
                );
            }
        );
    }

    function renderIndustryProfile() {
        var profile = profileForSelection();

        if (!profile) {
            if (profilePanel) {
                profilePanel.classList.add('tw-hidden');
            }
            if (questionsPanel) {
                questionsPanel.innerHTML = '';
            }
            updateOnboardingProgress();
            return;
        }

        if (profilePanel) {
            profilePanel.querySelector('[data-industry-profile-name]').textContent = profile.name || '';
            profilePanel.querySelector('[data-industry-profile-audience]').textContent = profile.audience || '';
            profilePanel.querySelector('[data-industry-profile-workspace]').textContent = profile.workspace || '';
            var featureSummary = profilePanel.querySelector('[data-industry-profile-features]');
            if (featureSummary) {
                featureSummary.textContent = (profile.features || []).join(', ');
            }
            profilePanel.classList.remove('tw-hidden');
        }

        if (!questionsPanel) {
            return;
        }

        questionsPanel.innerHTML = (profile.questions || []).map(renderQuestion).join('');
        applyQuestionDependencies();
        updateOnboardingProgress();
    }

    function namedFieldComplete(name) {
        var fields = fieldsNamed(name).filter(function (field) { return !field.disabled; });
        if (!fields.length) {
            return true;
        }
        if (fields[0].type === 'checkbox' || fields[0].type === 'radio') {
            return fields.some(function (field) { return field.checked; });
        }
        return String(fields[0].value || '').trim() !== '';
    }

    function operationsComplete() {
        var profile = profileForSelection();
        if (!selector || !selector.value || !profile) {
            return false;
        }

        return (profile.questions || []).every(function (question) {
            if (!question.required || (question.type || 'boolean') === 'boolean') {
                return true;
            }
            if (question.type === 'multiselect') {
                var selected = form.querySelectorAll('[name="onboarding[' + question.key + '][]"]:checked:not(:disabled)');
                return selected.length >= Number(question.min || 1);
            }
            return namedFieldComplete('onboarding[' + question.key + ']');
        });
    }

    function updateOnboardingProgress() {
        var steps = Array.prototype.slice.call(form.querySelectorAll('[data-registration-step]'));
        if (!steps.length) {
            return;
        }

        var companyNames = ['name', 'country', 'city', 'landmark', 'currency_id', 'time_zone'];
        var ownerNames = ['first_name', 'username', 'email', 'password', 'confirm_password']
            .filter(function (name) { return fieldsNamed(name).length > 0; });
        var companyComplete = companyNames.every(namedFieldComplete);
        var operationComplete = operationsComplete();
        var ownerComplete = ownerNames.length
            ? ownerNames.every(namedFieldComplete)
            : companyComplete && operationComplete;
        var states = [companyComplete, operationComplete, ownerComplete];
        var firstIncomplete = states.indexOf(false);

        steps.forEach(function (step, index) {
            step.classList.toggle('is-complete', Boolean(states[index]));
            step.classList.toggle('is-active', index === firstIncomplete || (firstIncomplete === -1 && index === steps.length - 1));
            if (index === firstIncomplete) {
                step.setAttribute('aria-current', 'step');
            } else {
                step.removeAttribute('aria-current');
            }
        });

        var required = Array.prototype.slice.call(form.querySelectorAll('[required]'))
            .filter(function (field) { return !field.disabled && field.type !== 'hidden'; });
        var completed = required.filter(function (field) {
            if (field.type === 'checkbox' || field.type === 'radio') {
                return namedFieldComplete(field.name);
            }
            return String(field.value || '').trim() !== '';
        }).length;
        var extraRequired = (profileForSelection() && profileForSelection().questions || [])
            .filter(function (question) { return question.required && question.type === 'multiselect'; });
        extraRequired.forEach(function (question) {
            required.push(question);
            var selected = form.querySelectorAll('[name="onboarding[' + question.key + '][]"]:checked:not(:disabled)');
            if (selected.length >= Number(question.min || 1)) {
                completed++;
            }
        });

        var progress = form.querySelector('[data-onboarding-submit-progress] span');
        if (progress) {
            progress.style.width = (required.length ? Math.round((completed / required.length) * 100) : 0) + '%';
        }
    }

    function serializableField(field) {
        if (!field.name || field.disabled) {
            return false;
        }

        if (field.type === 'password' || field.type === 'file' || field.type === 'submit') {
            return false;
        }

        return !['accept_tc', 'g-recaptcha-response', '_token'].includes(field.name);
    }

    function saveDraft() {
        if (!draftKey || !window.localStorage) {
            return;
        }

        var draft = { savedAt: new Date().toISOString(), values: {} };
        Array.prototype.forEach.call(form.elements, function (field) {
            if (!serializableField(field)) {
                return;
            }

            if (field.type === 'hidden' && form.querySelector('[type="checkbox"][name="' + field.name + '"]')) {
                return;
            }

            if (field.type === 'checkbox' && /\[\]$/.test(field.name)) {
                draft.values[field.name] = draft.values[field.name] || [];
                if (field.checked) {
                    draft.values[field.name].push(field.value);
                }
            } else if (field.type === 'checkbox' || field.type === 'radio') {
                draft.values[field.name] = field.checked ? field.value : '0';
            } else {
                draft.values[field.name] = field.value;
            }
        });

        try {
            window.localStorage.setItem(draftKey, JSON.stringify(draft));
        } catch (error) {
            // Private browsing or storage policies may disable localStorage.
        }
    }

    function restoreDraft() {
        if (!draftKey || !window.localStorage || form.querySelector('.alert-danger')) {
            return;
        }

        var draft;
        try {
            draft = JSON.parse(window.localStorage.getItem(draftKey) || 'null');
        } catch (error) {
            draft = null;
        }

        if (!draft || !draft.values) {
            return;
        }

        Object.keys(draft.values).forEach(function (name) {
            fieldsNamed(name).forEach(function (field) {
                if (!serializableField(field)) {
                    return;
                }
                if (field.type === 'checkbox' || field.type === 'radio') {
                    var saved = draft.values[name];
                    field.checked = Array.isArray(saved)
                        ? saved.map(String).indexOf(String(field.value)) !== -1
                        : String(field.value) === String(saved);
                } else {
                    field.value = draft.values[name];
                }
            });
        });

        restoredDraft = true;
        oldAnswers = Object.keys(draft.values).reduce(function (answers, name) {
            var match = name.match(/^onboarding\[([^\]]+)\](\[\])?$/);
            if (match) {
                answers[match[1]] = Array.isArray(draft.values[name])
                    ? draft.values[name]
                    : draft.values[name];
            }
            return answers;
        }, oldAnswers);

        if ($ && selector) {
            $(selector).trigger('change.select2');
        }
    }

    function addDraftNotice() {
        if (!restoredDraft) {
            return;
        }

        var notice = document.createElement('div');
        notice.className = 'alert alert-success';
        notice.setAttribute('role', 'status');
        notice.innerHTML = 'Your saved draft was restored on this device. ' +
            '<button type="button" class="btn btn-link btn-xs" data-discard-draft>Discard draft</button>';
        form.insertBefore(notice, form.firstChild);

        notice.querySelector('[data-discard-draft]').addEventListener('click', function () {
            window.localStorage.removeItem(draftKey);
            window.location.reload();
        });
    }

    function detectTimezone() {
        var timezone = form.querySelector('[name="time_zone"]');
        if (!timezone || timezone.value !== '') {
            return;
        }

        try {
            var detected = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var supported = Array.prototype.some.call(timezone.options, function (option) {
                return option.value === detected;
            });
            if (detected && supported) {
                timezone.value = detected;
                if ($) {
                    $(timezone).trigger('change.select2');
                }
            }
        } catch (error) {
            // Keep the server default when timezone detection is unavailable.
        }
    }

    function normalizeWebsite(event) {
        var field = event.target;
        if (field.name !== 'website' || !field.value || /^[a-z][a-z0-9+.-]*:\/\//i.test(field.value)) {
            return;
        }
        field.value = 'https://' + field.value.trim();
    }

    function updatePasswordStrength() {
        var password = form.querySelector('[name="password"]');
        var bar = form.querySelector('[data-password-strength]');
        if (!password || !bar) {
            return;
        }

        var value = password.value;
        var score = [value.length >= 8, /[a-z]/.test(value), /[A-Z]/.test(value), /\d/.test(value), /[^A-Za-z0-9]/.test(value)]
            .filter(Boolean).length;
        var width = value ? Math.max(20, score * 20) : 0;
        bar.style.width = width + '%';
        bar.className = 'progress-bar ' + (score < 3 ? 'progress-bar-danger' : (score < 5 ? 'progress-bar-warning' : 'progress-bar-success'));
    }

    restoreDraft();
    renderIndustryProfile();
    addDraftNotice();
    detectTimezone();
    updateOnboardingProgress();

    if (selector) {
        selector.addEventListener('change', function () {
            oldAnswers = {};
            renderIndustryProfile();
            saveDraft();
            updateOnboardingProgress();
        });
    }

    form.addEventListener('input', function (event) {
        if (event.target.name === 'password') {
            updatePasswordStrength();
        }
        window.clearTimeout(form.__draftTimer);
        form.__draftTimer = window.setTimeout(saveDraft, 400);
        updateOnboardingProgress();
    });
    form.addEventListener('change', function () {
        applyQuestionDependencies();
        saveDraft();
        updateOnboardingProgress();
    });
    form.addEventListener('blur', normalizeWebsite, true);
    form.addEventListener('submit', function () {
        if (draftKey && window.localStorage) {
            window.localStorage.removeItem(draftKey);
        }
        var submit = form.querySelector('[type="submit"]');
        if (submit) {
            submit.disabled = true;
            submit.setAttribute('aria-busy', 'true');
        }
    });
})(window, document, window.jQuery);
