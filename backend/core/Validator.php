<?php
class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if (trim((string)$value) === '') {
            $this->errors[$field][] = "{$label} is required";
        }
        return $this;
    }

    public function email(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "{$label} must be a valid email";
        }
        return $this;
    }

    public function min(string $field, int $length, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if (mb_strlen((string)$value) < $length) {
            $this->errors[$field][] = "{$label} must be at least {$length} characters";
        }
        return $this;
    }

    public function max(string $field, int $length, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if (mb_strlen((string)$value) > $length) {
            $this->errors[$field][] = "{$label} must not exceed {$length} characters";
        }
        return $this;
    }

    public function numeric(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !is_numeric($value)) {
            $this->errors[$field][] = "{$label} must be numeric";
        }
        return $this;
    }

    public function regex(string $field, string $pattern, ?string $label = null, ?string $message = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !preg_match($pattern, (string)$value)) {
            $this->errors[$field][] = $message ?? "{$label} has an invalid format";
        }
        return $this;
    }

    public function matches(string $field, string $other, ?string $message = null): self
    {
        if (isset($this->data[$field], $this->data[$other])
            && (string)$this->data[$field] !== (string)$this->data[$other]) {
            $this->errors[$field][] = $message ?? "{$field} does not match {$other}";
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !in_array($value, $allowed)) {
            $this->errors[$field][] = "{$label} must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function date(string $field, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if ($value !== '' && !strtotime($value)) {
            $this->errors[$field][] = "{$label} must be a valid date";
        }
        return $this;
    }

    public function unique(string $field, string $table, string $column, ?int $ignoreId = null, ?string $label = null): self
    {
        $label = $label ?? $field;
        $value = $this->data[$field] ?? '';
        if ($value === '') return $this;
        // Whitelist table/column identifiers to prevent SQL injection via method arguments.
        $allowedTables = ['users', 'customers', 'organizations', 'vendors', 'manual_devices', 'knowledge_articles', 'ci_items', 'csi_register'];
        $allowedColumns = ['id', 'email', 'name', 'slug', 'serial_number', 'ticket_number'];
        if (!in_array($table, $allowedTables, true) || !in_array($column, $allowedColumns, true)) {
            $this->errors[$field][] = "{$label} validation failed";
            return $this;
        }
        $sql = "SELECT 1 FROM {$table} WHERE {$column} = ?";
        $params = [$value];
        if ($ignoreId) {
            $sql .= " AND id != ?";
            $params[] = $ignoreId;
        }
        $result = Database::fetch($sql . " LIMIT 1", $params);
        if ($result) {
            $this->errors[$field][] = "{$label} is already taken";
        }
        return $this;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    public function validate(): array
    {
        if (!$this->passes()) {
            Response::error('Validation failed', 422, $this->errors);
        }
        return $this->data;
    }
}
