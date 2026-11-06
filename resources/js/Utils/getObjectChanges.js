const normalize = (v) => (v === null || v === undefined ? null : v);

/**
 * Compute changes between original data and edited data.
 *
 * @param {Object} editedRows - Object where keys are row IDs and values are objects of edited fields.
 * @param {Object} originalData - Object where keys are row IDs and values are objects of original fields.
 * @returns {Array} Array of change objects: { rowId, field, before, after }
 */
export default function getObjectChanges(editedRows, originalData) {
  const changes = [];

  function compareObjects(rowId, original, edited, parentField = "") {
    for (const key in edited) {
      const before = original[key];
      const after = edited[key];

      const beforeN = normalize(before);
      const afterN = normalize(after);

      const fieldPath = parentField ? `${parentField}.${key}` : key;

      if (beforeN && typeof beforeN === "object" &&
          afterN && typeof afterN === "object") {
        compareObjects(rowId, beforeN, afterN, fieldPath);
      } else if (beforeN !== afterN) {
        changes.push({ rowId, field: fieldPath, before, after });
      }
    }
  }

  for (const rowId in editedRows) {
    const original = originalData[rowId] || {};
    const edited = editedRows[rowId];
    compareObjects(rowId, original, edited);
  }

  return changes;
}