import { describe, expect, it } from 'vitest'
import Keen, { screens } from '../src/atrium'
import { expectIntegrationManifest } from '@refactor-circus/atrium/testing'

/*
 * Keen's integration manifest (`src/integration.json`, built to
 * `dist/atrium/integration.json`): valid as Atrium checks it, and every
 * screen, hook section/tab and live resolver it declares is one the module
 * registers.
 */

const manifest = expectIntegrationManifest('src/integration.json', 'keen')

type Hook = { sections?: Record<string, unknown>, tabs?: Record<string, unknown> }
type Navigation = { live?: { key: string } }
type Widget = { component: string }

const hooks = (manifest.hooks ?? []) as Hook[]
const hookKeys = hooks.flatMap(hook => [...Object.keys(hook.sections ?? {}), ...Object.keys(hook.tabs ?? {})])
const liveKeys = ((manifest.navigation ?? []) as Navigation[]).flatMap(item => item.live === undefined ? [] : [item.live.key])
const widgetKeys = ((manifest.widgets ?? []) as Widget[]).map(widget => widget.component)

describe('the Keen integration manifest', () => {
  it('declares the keen integration, version 1', () => {
    expect(manifest.key).toBe('keen')
    expect(manifest.version).toBe(1)
  })

  it('maps every declared screen to a module page, and no other', () => {
    expect(Object.keys(screens).sort()).toEqual((manifest.screens ?? []).map(screen => screen.key).sort())
  })

  it('registers every hook section and tab it declares', () => {
    expect(hookKeys.sort()).toEqual(['keen.activity', 'keen.history'])

    for (const key of hookKeys) {
      expect(Keen.extensions?.[key], key).toBeDefined()
    }
  })

  it('registers every live resolver and widget component it declares', () => {
    expect(liveKeys).toEqual(['keen.history'])

    for (const key of liveKeys) {
      expect(Keen.liveResolvers?.[key], key).toBeDefined()
    }

    for (const key of widgetKeys) {
      expect(Keen.widgets?.[key], key).toBeDefined()
    }
  })
})
