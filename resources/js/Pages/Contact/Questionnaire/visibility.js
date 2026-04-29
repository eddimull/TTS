/**
 * Mirrors app/Services/QuestionnaireVisibilityEvaluator.php exactly.
 * Any change here MUST be made in the PHP file too.
 *
 * @param {number} fieldId  The field whose visibility we're evaluating
 * @param {Array<{id:number,visibility_rule:object|null}>} allFields
 * @param {Object<number,*>} responses  Keyed by field id
 */
export function isFieldVisible(fieldId, allFields, responses) {
  const field = findField(fieldId, allFields)
  if (!field) return true
  return fieldIsVisible(field, allFields, responses)
}

function fieldIsVisible(field, allFields, responses) {
  const rule = field.visibility_rule
  if (!rule) return true

  const targetId = rule.depends_on
  const target = findField(targetId, allFields)
  if (!target) return true

  if (!fieldIsVisible(target, allFields, responses)) return false

  const value = responses[targetId] ?? null
  return evaluate(rule, value)
}

function findField(id, allFields) {
  return allFields.find(f => f.id === id) ?? null
}

function evaluate(rule, value) {
  const expected = rule.value ?? null
  switch (rule.operator) {
    case 'equals': return valueEquals(value, expected)
    case 'not_equals': return !valueEquals(value, expected)
    case 'contains': return valueContains(value, expected)
    case 'empty': return valueIsEmpty(value)
    case 'not_empty': return !valueIsEmpty(value)
    default: return false
  }
}

function valueEquals(value, expected) {
  if (Array.isArray(value)) return value.includes(expected)
  return String(value) === String(expected)
}

function valueContains(value, expected) {
  const needle = String(expected)
  if (Array.isArray(value)) {
    return value.some(item => typeof item === 'string' && item.includes(needle))
  }
  return typeof value === 'string' && value.includes(needle)
}

function valueIsEmpty(value) {
  if (Array.isArray(value)) return value.length === 0
  return value === null || value === undefined || value === ''
}
