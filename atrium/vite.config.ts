import { join } from 'node:path'
import ui from '@nuxt/ui/vite'
import { ATRIUM_SDK_ROOT, atriumAutoImports, atriumComponentResolver, atriumModule, atriumUiConfig } from '@refactor-circus/atrium/vite'

// Keen's Atrium module: `pnpm build` writes ../dist/atrium (committed), `pnpm test` runs Vitest.
const config = atriumModule({
  key: 'keen',
  outDir: '../dist/atrium',
  inputs: ['src', 'package.json', 'vite.config.ts', 'pnpm-lock.yaml'],
  test: { setupFiles: ['test/setup.ts'] }
})

// TEMPORARY, until Atrium's preset fixes `nuxtUi()`: installed from git, the
// SDK sits under node_modules, which Nuxt UI's component resolver skips by
// default (its exclude array is merged with ours, so it must be one RegExp).
// The SDK's own <AtriumScreen>/<AtriumNoAccess> then never resolve in tests.
// This is the preset's `nuxtUi()` with that exclude replaced; the build is
// unaffected. Drop it when the SDK pin includes the fix.
if (process.env.VITEST) {
  const sdkSources = new RegExp(`^${join(ATRIUM_SDK_ROOT, 'src').replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}[\\\\/]`)

  config.plugins = [
    config.plugins![0],
    ui({
      ui: atriumUiConfig,
      colorMode: true,
      autoImport: { imports: atriumAutoImports({ ui: false }) as never, dts: false, vueTemplate: true, exclude: [/[\\/]node_modules[\\/]/, /[\\/]\.git[\\/]/, sdkSources] },
      components: { dirs: [], resolvers: [atriumComponentResolver({ ui: false, direct: true })], dts: false, exclude: /[\\/]node_modules[\\/](?!\.pnpm[\\/]|@nuxt[\\/]ui[\\/]|@refactor-circus[\\/]atrium[\\/])/ }
    })
  ]
}

export default config
