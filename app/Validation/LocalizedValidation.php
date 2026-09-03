<?php

namespace App\Validation;

use CodeIgniter\Validation\Validation;
use CodeIgniter\Validation\ValidationInterface;

/** Give controller and model validation the same human-readable field labels. */
class LocalizedValidation extends Validation
{
    public function setRules(array $rules, array $errors = []): ValidationInterface
    {
        parent::setRules($rules, $errors);
        foreach ($this->rules as $field => &$rule) {
            if (! isset($rule['label']) || $rule['label'] === $field) {
                $key = 'Ui.fields.' . $field;
                $rule['label'] = lang($key) !== $key ? $key : 'Ui.fields.value';
            }
        }

        return $this;
    }
}
