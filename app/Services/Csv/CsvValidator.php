<?php

namespace App\Services\Csv;

use App\Exceptions\ValidationException;

class CsvValidator
{
    /**
     * Validate overall CSV schema
     */
    public function validateSchema(array $rows): bool
    {
        if (empty($rows)) {
            throw new ValidationException('CSV must contain at least one data row');
        }

        if (count($rows) > 50) {
            throw new ValidationException('CSV cannot contain more than 50 tickets');
        }

        $allErrors = [];
        foreach ($rows as $index => $row) {
            $errors = $this->validateRow($row);
            if (!empty($errors)) {
                $allErrors["row_" . ($index + 1)] = $errors; // +1 because rows are 0-indexed but users expect 1-indexed
            }
        }

        if (!empty($allErrors)) {
            throw new ValidationException('CSV validation failed', $allErrors);
        }

        return true;
    }

    /**
     * Validate a single row and return errors array
     */
    public function validateRow(array $row): array
    {
        $errors = [];

        // Required fields validation
        $requiredFields = ['issue_key', 'summary', 'description', 'reporter'];
        foreach ($requiredFields as $field) {
            if (empty(trim($row[$field] ?? ''))) {
                $errors[] = "Field '{$field}' is required";
            }
        }

        // Field-specific validations
        if (isset($row['issue_key'])) {
            $error = $this->validateField('issue_key', $row['issue_key']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['summary'])) {
            $error = $this->validateField('summary', $row['summary']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['description'])) {
            $error = $this->validateField('description', $row['description']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['reporter'])) {
            $error = $this->validateField('reporter', $row['reporter']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['assignee']) && !empty(trim($row['assignee']))) {
            $error = $this->validateField('assignee', $row['assignee']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['priority']) && !empty(trim($row['priority']))) {
            $error = $this->validateField('priority', $row['priority']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['status']) && !empty(trim($row['status']))) {
            $error = $this->validateField('status', $row['status']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['created']) && !empty(trim($row['created']))) {
            $error = $this->validateField('created', $row['created']);
            if ($error) {
                $errors[] = $error;
            }
        }

        if (isset($row['labels']) && !empty(trim($row['labels']))) {
            $error = $this->validateField('labels', $row['labels']);
            if ($error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * Validate a specific field and return error message or null
     */
    public function validateField(string $fieldName, string $value): ?string
    {
        $value = trim($value);

        switch ($fieldName) {
            case 'issue_key':
                return $this->validateIssueKey($value);

            case 'summary':
                return $this->validateSummary($value);

            case 'description':
                return $this->validateDescription($value);

            case 'reporter':
            case 'assignee':
                return $this->validateEmail($value, $fieldName);

            case 'priority':
                return $this->validatePriority($value);

            case 'status':
                return $this->validateStatus($value);

            case 'created':
                return $this->validateCreated($value);

            case 'labels':
                return $this->validateLabels($value);

            default:
                return null;
        }
    }

    /**
     * Validate issue key format (PROJ-123)
     */
    private function validateIssueKey(string $value): ?string
    {
        if (strlen($value) > 20) {
            return 'Issue key cannot exceed 20 characters';
        }

        if (!preg_match('/^[A-Z]+-[0-9]+$/', $value)) {
            return 'Issue key must be alphanumeric with hyphen format (e.g., PROJ-123)';
        }

        return null;
    }

    /**
     * Validate summary field
     */
    private function validateSummary(string $value): ?string
    {
        if (strlen($value) < 5) {
            return 'Summary must be at least 5 characters long';
        }

        if (strlen($value) > 200) {
            return 'Summary cannot exceed 200 characters';
        }

        return null;
    }

    /**
     * Validate description field
     */
    private function validateDescription(string $value): ?string
    {
        if (strlen($value) < 10) {
            return 'Description must be at least 10 characters long';
        }

        if (strlen($value) > 2000) {
            return 'Description cannot exceed 2000 characters';
        }

        return null;
    }

    /**
     * Validate email field
     */
    private function validateEmail(string $value, string $fieldName): ?string
    {
        if (strlen($value) > 255) {
            return ucfirst($fieldName) . ' email cannot exceed 255 characters';
        }

        // Basic email validation
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ucfirst($fieldName) . ' must be a valid email address';
        }

        // Check for disposable emails
        if ($this->isDisposableEmail($value)) {
            return 'Disposable email addresses are not allowed';
        }

        return null;
    }

    /**
     * Validate priority field
     */
    private function validatePriority(string $value): ?string
    {
        $validPriorities = ['Critical', 'High', 'Medium', 'Low'];
        if (!in_array($value, $validPriorities)) {
            return 'Priority must be one of: ' . implode(', ', $validPriorities);
        }

        return null;
    }

    /**
     * Validate status field
     */
    private function validateStatus(string $value): ?string
    {
        $validStatuses = ['Open', 'In Progress', 'Resolved', 'Closed'];
        if (!in_array($value, $validStatuses)) {
            return 'Status must be one of: ' . implode(', ', $validStatuses);
        }

        return null;
    }

    /**
     * Validate created timestamp
     */
    private function validateCreated(string $value): ?string
    {
        // Try to parse as ISO 8601
        $date = date_create_from_format('Y-m-d\TH:i:s\Z', $value) ?:
                date_create_from_format('Y-m-d\TH:i:sP', $value) ?:
                date_create($value);

        if (!$date) {
            return 'Created date must be a valid ISO 8601 timestamp';
        }

        // Check if date is not in the future
        if ($date > new \DateTime()) {
            return 'Created date cannot be in the future';
        }

        return null;
    }

    /**
     * Validate labels field
     */
    private function validateLabels(string $value): ?string
    {
        if (strlen($value) > 255) {
            return 'Labels cannot exceed 255 characters';
        }

        // Check format (semicolon-separated, alphanumeric + allowed chars)
        if (!preg_match('/^[a-zA-Z0-9;,\-\s]+$/', $value)) {
            return 'Labels must contain only letters, numbers, semicolons, commas, hyphens, and spaces';
        }

        return null;
    }

    /**
     * Check if email is from disposable email service
     */
    private function isDisposableEmail(string $email): bool
    {
        $disposableDomains = [
            'temp-mail.org',
            '10minutemail.com',
            'guerrillamail.com',
            'mailinator.com',
            'throwaway.email',
            'getnada.com',
            'temp-mail.io',
            'yopmail.com',
            'maildrop.cc',
            'tempail.com'
        ];

        $domain = strtolower(substr(strrchr($email, '@'), 1));

        return in_array($domain, $disposableDomains);
    }
}
