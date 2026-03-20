import { createAppConfig } from '@nextcloud/vite-config'

export default createAppConfig(
  {
    main:     'src/main.js',
    settings: 'src/settings.js',
  },
  {
    config: {
      build: { sourcemap: false },
    },
  }
)
