import { describe, it, expect } from 'vitest'
import { isFieldVisible } from '../visibility.js'

describe('visibility evaluator (JS)', () => {
  function fields(arr) {
    return arr
  }

  it('field is visible when no rule set', () => {
    const f = fields([{ id: 1, visibility_rule: null }])
    expect(isFieldVisible(1, f, {})).toBe(true)
  })

  it('equals operator for single-value field', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'equals', value: 'yes' } },
    ])
    expect(isFieldVisible(2, f, { 1: 'yes' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'no' })).toBe(false)
  })

  it('equals operator for multi-value field', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'equals', value: 'rock' } },
    ])
    expect(isFieldVisible(2, f, { 1: ['rock', 'jazz'] })).toBe(true)
    expect(isFieldVisible(2, f, { 1: ['pop'] })).toBe(false)
  })

  it('not_equals operator', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'not_equals', value: 'no' } },
    ])
    expect(isFieldVisible(2, f, { 1: 'yes' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'no' })).toBe(false)
  })

  it('contains for string and array', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'contains', value: 'cake' } },
    ])
    expect(isFieldVisible(2, f, { 1: 'I want cake' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'just drinks' })).toBe(false)
    expect(isFieldVisible(2, f, { 1: ['I want cake', 'plus drinks'] })).toBe(true)
    expect(isFieldVisible(2, f, { 1: ['just drinks'] })).toBe(false)
  })

  it('empty and not_empty operators', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'empty', value: null } },
      { id: 3, visibility_rule: { depends_on: 1, operator: 'not_empty', value: null } },
    ])
    expect(isFieldVisible(2, f, { 1: '' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: null })).toBe(true)
    expect(isFieldVisible(2, f, { 1: [] })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'x' })).toBe(false)
    expect(isFieldVisible(3, f, { 1: 'x' })).toBe(true)
  })

  it('field is hidden when controller is hidden transitively', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'equals', value: 'hide' } },
      { id: 3, visibility_rule: { depends_on: 2, operator: 'not_empty', value: null } },
    ])
    expect(isFieldVisible(3, f, { 1: 'show', 2: 'anything' })).toBe(false)
    expect(isFieldVisible(3, f, { 1: 'hide', 2: 'anything' })).toBe(true)
    expect(isFieldVisible(3, f, { 1: 'hide', 2: '' })).toBe(false)
  })

  it('returns true when target field does not exist', () => {
    const f = fields([
      { id: 2, visibility_rule: { depends_on: 999, operator: 'equals', value: 'x' } },
    ])
    expect(isFieldVisible(2, f, {})).toBe(true)
  })
})
