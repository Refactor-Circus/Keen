import { rawHtmlIn } from '@refactor-circus/atrium/testing'
import { expect, it } from 'vitest'

/*
 * Keen's module composes Nuxt UI, as Atrium's own components do (Atrium
 * D-091): no page or component in `src` renders a raw `<button>`, `<input>`,
 * `<select>`, `<textarea>`, `<table>`, `<details>` or `<a href>`.
 */
it('composes Nuxt UI, with no raw HTML controls (D-091)', () => {
  expect(rawHtmlIn('src')).toEqual([])
})
