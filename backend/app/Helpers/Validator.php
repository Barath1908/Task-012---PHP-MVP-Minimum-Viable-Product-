<?php

// ============================================================
//  Validator.php — Input Validation
//  Usage:
//    $validator = new Validator($data);
//    $validator->required(['email','password'])
//      ->email('email')
//      ->min('password', 8);
//    if ($validator->fails()) Response::validationError($validator->errors());
// ============================================================

class Validator
{
    private array $data;
    private array $errors = [];

    // --------------------------------------------------------
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // --------------------------------------------------------
    //  required()
    //  All listed fields must be present and non-empty.
    // --------------------------------------------------------
    public function required(array $fields): static
    {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
                $this->errors[$field][] = "{$field} is required";
            }
        }
        return $this;
    }

    // --------------------------------------------------------
    //  email()
    //  Field must be a valid email format.
    // --------------------------------------------------------
    public function email(string $field): static
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "{$field} must be a valid email address";
        }
        return $this;
    }

    // --------------------------------------------------------
    //  min()
    //  String length must be >= $length.
    // --------------------------------------------------------
    public function min(string $field, int $length): static
    {
        if (isset($this->data[$field]) && strlen((string)$this->data[$field]) < $length) {
            $this->errors[$field][] = "{$field} must be at least {$length} characters";
        }
        return $this;
    }

    // --------------------------------------------------------
    //  max()
    //  String length must be <= $length.
    // --------------------------------------------------------
    public function max(string $field, int $length): static
    {
        if (isset($this->data[$field]) && strlen((string)$this->data[$field]) > $length) {
            $this->errors[$field][] = "{$field} must not exceed {$length} characters";
        }
        return $this;
    }

    // --------------------------------------------------------
    //  numeric()
    //  Field must be a numeric value.
    // --------------------------------------------------------
    public function numeric(string $field): static
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field][] = "{$field} must be a number";
        }
        return $this;
    }

    // --------------------------------------------------------
    //  in()
    //  Field value must be one of the allowed values.
    // --------------------------------------------------------
    public function in(string $field, array $allowed): static
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, strict: true)) {
            $this->errors[$field][] = "{$field} must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    // --------------------------------------------------------
    //  regex()
    //  Field must match the given regex pattern.
    // --------------------------------------------------------
    public function regex(string $field, string $pattern, string $message = ''): static
    {
        if (isset($this->data[$field]) && !preg_match($pattern, (string)$this->data[$field])) {
            $this->errors[$field][] = $message ?: "{$field} format is invalid";
        }
        return $this;
    }

    // --------------------------------------------------------
    //  confirmed()
    //  Field must match field_confirmation.
    //  e.g. password must match password_confirmation.
    // --------------------------------------------------------
    public function confirmed(string $field): static
    {
        $confirm = $field . '_confirmation';
        if (
            isset($this->data[$field], $this->data[$confirm]) &&
            $this->data[$field] !== $this->data[$confirm]
        ) {
            $this->errors[$field][] = "{$field} confirmation does not match";
        }
        return $this;
    }

    // --------------------------------------------------------
    //  date()
    //  Field must be a valid date string (Y-m-d).
    // --------------------------------------------------------
    public function date(string $field): static
    {
        if (isset($this->data[$field])) {
            $d = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$d || $d->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field][] = "{$field} must be a valid date (YYYY-MM-DD)";
            }
        }
        return $this;
    }

    // --------------------------------------------------------
    //  fails() / passes()
    // --------------------------------------------------------
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    // --------------------------------------------------------
    //  errors()
    //  Returns all validation errors as associative array.
    // --------------------------------------------------------
    public function errors(): array
    {
        return $this->errors;
    }

    // --------------------------------------------------------
    //  get()
    //  Safe getter — returns field value or default.
    // --------------------------------------------------------
    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }
}
