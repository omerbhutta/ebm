<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight input validator. Usage:
 *   $v = Validator::make($data, ['email' => 'required|email', 'name' => 'required|max:120']);
 *   if (!$v->ok()) { ... $v->errors() ... }
 */
final class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function ok(): bool { return empty($this->errors); }
    public function errors(): array { return $this->errors; }
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
    public function data(): array { return $this->data; }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleStr) {
            $value = $this->data[$field] ?? null;
            $rules = is_array($ruleStr) ? $ruleStr : explode('|', $ruleStr);
            $isRequired = in_array('required', $rules, true);
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');

            if ($isEmpty && !$isRequired) continue;

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($field, $value, $name, $param);
                if (isset($this->errors[$field])) break;
            }
        }
    }

    private function applyRule(string $field, $value, string $rule, ?string $param): void
    {
        switch ($rule) {
            case 'required':
                if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && empty($value))) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' is required.');
                }
                break;
            case 'email':
                if (!filter_var((string)$value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' must be a valid email.');
                }
                break;
            case 'min':
                $len = is_string($value) ? mb_strlen($value) : (int)$value;
                if ($len < (int)$param) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . " must be at least {$param} characters.");
                }
                break;
            case 'max':
                $len = is_string($value) ? mb_strlen($value) : (int)$value;
                if ($len > (int)$param) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . " may not be longer than {$param} characters.");
                }
                break;
            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' must be numeric.');
                }
                break;
            case 'integer':
                if (!filter_var($value, FILTER_VALIDATE_INT) && $value !== '0' && $value !== 0) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' must be an integer.');
                }
                break;
            case 'url':
                if (!filter_var((string)$value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' must be a valid URL.');
                }
                break;
            case 'in':
                $vals = explode(',', (string)$param);
                if (!in_array((string)$value, $vals, true)) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' is invalid.');
                }
                break;
            case 'match':
                if (($this->data[$param] ?? null) !== $value) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . " must match {$param}.");
                }
                break;
            case 'regex':
                if (!preg_match($param, (string)$value)) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' is not in the correct format.');
                }
                break;
            case 'uuid':
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string)$value)) {
                    $this->addError($field, ucfirst(str_replace('_',' ',$field)) . ' must be a valid GUID/UUID.');
                }
                break;
        }
    }

    private function addError(string $field, string $msg): void
    {
        if (!isset($this->errors[$field])) $this->errors[$field] = [];
        $this->errors[$field][] = $msg;
    }
}
