<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;

class BulkUpserter
{
  protected Model $model;
  protected array $dateColumns = [];
  protected array $columnRules = [];
  protected array $columnHandlers = [];
  protected array $attributeNames = [];

  public function __construct(
    Model $model,
    array $columnRules = [],
    array $dateColumns = [],
    array $columnHandlers = [],
    array $attributeNames = []
  ) {
    $this->model = $model;
    $this->columnRules = $columnRules;
    $this->dateColumns = $dateColumns;
    $this->columnHandlers = $columnHandlers;
    $this->attributeNames = $attributeNames;
  }

  protected function isNewRow($id): bool
  {
    if ($id === null) return true;
    return is_string($id) && str_starts_with($id, 'new-');
  }

  public function update(array $rows): array
  {
    $errors = [];
    $errorMessages = [];
    $inserted = [];
    $insertedByRowId = [];
    $updated = [];

    // all-or-nothing: if any row fails validation, nothing is written
    DB::beginTransaction();
    try {
      foreach ($rows as $row) {
        $id = $row['id'] ?? null;
        $fields = $row;
        if (empty($fields)) continue;
        $isNew = $this->isNewRow($id);

        $fieldsForValidation = $fields;

        foreach ($fieldsForValidation as $column => $value) {
          if (isset($this->columnHandlers[$column]) && is_callable($this->columnHandlers[$column])) {
            $fieldsForValidation[$column] = call_user_func($this->columnHandlers[$column], $value);
          }
        }

        $rules = [];
        foreach ($this->columnRules as $column => $rule) {
          $rules[$column] = $rule instanceof \Closure
            ? $rule($isNew ? null : $id, $fieldsForValidation)
            : $rule;
        }

        $validator = Validator::make(
          $fieldsForValidation,
          $rules,
          [
            '*.integer' => 'Invalid value :input for column :attribute. Must be an integer.',
            '*.string' => 'Invalid value :input for column :attribute. Must be a string.',
            '*.date' => 'Invalid date :input for column :attribute.',
            '*.exists' => 'Value :input for column :attribute does not exist.',
            '*.unique' => 'Value :input for column :attribute already exists.',
          ],
          $this->attributeNames
        );

        if ($validator->fails()) {
          $errors[$id] = $validator->errors()->messages();
          continue;
        }

        $normalizedData = $this->normalizeFields($fields);

        if ($isNew) {
          $modelInstance = $this->model->create($normalizedData);
          $inserted[] = $modelInstance;
          $insertedByRowId[(string) $id] = $modelInstance;
        } else {
          $modelInstance = $this->model->find($id);
          if (!$modelInstance) continue;
          $modelInstance->update($normalizedData);
          $updated[] = $modelInstance;
        }
      }

      if (!empty($errors)) {
        DB::rollBack();
        $inserted = [];
        $insertedByRowId = [];
        $updated = [];
      } else {
        DB::commit();
      }
    } catch (\Throwable $e) {
      DB::rollBack();
      throw $e;
    }

    if (!empty($errors)) {
      $maxErrorRows = 20;

      foreach ($errors as $rowId => $columns) {
        foreach ($columns as $column => $messages) {
          foreach ($messages as $msg) {
            $errorMessages[] = "Row " . ($rowId ?? '?') . ": {$msg}";

            if (count($errorMessages) >= $maxErrorRows) {
              break 3;
            }
          }
        }
      }
    }

    return [
      'updated' => $updated,
      'inserted' => $inserted,
      'insertedByRowId' => $insertedByRowId,
      'errors' => $errors,
      'errorMessages' => $errorMessages
    ];
  }

  protected function normalizeFields(array $fields): array
  {
    $normalized = [];

    foreach ($fields as $column => $value) {

      if (in_array($column, $this->dateColumns)) {
        $normalized[$column] = $this->normalizeDate($value);
        continue;
      }

      // {id: 5, ...} relation objects collapse to their id, but never JSON/array-cast columns
      if (
        is_array($value) && isset($value['id'])
        && !$this->model->hasCast($column, ['array', 'json', 'object', 'collection'])
      ) {
        $normalized[$column] = $value['id'];
        continue;
      }

      $normalized[$column] = $value;
    }

    return $normalized;
  }

  protected function normalizeDate(string|null $value): ?string
  {
    if ($value === null) return null;

    $value = trim($value);

    if (strlen($value) === 10) {
      $dt = \DateTime::createFromFormat('Y-m-d', $value);
      return $dt ? $dt->format('Y-m-d') : null;
    }

    if (strlen($value) === 16) {
      $dt = \DateTime::createFromFormat('Y-m-d H:i', str_replace('T', ' ', $value));
      return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    if (strlen($value) === 19) {
      $dt = \DateTime::createFromFormat('Y-m-d H:i:s', str_replace('T', ' ', $value));
      return $dt ? $dt->format('Y-m-d H:i:s') : null;
    }

    return null;
  }
}
