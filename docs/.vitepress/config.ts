import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Howl',
  description: 'Multi-driver Laravel notifier (Discord, Slack, Telegram) with rich embeds, channel failover, and queue-aware dispatch.',
  base: '/',
  ignoreDeadLinks: true,

  head: [
    // Favicons
    ['link', { rel: 'icon', type: 'image/png', href: '/favicon-96x96.png', sizes: '96x96' }],
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' }],
    ['link', { rel: 'shortcut icon', href: '/favicon.ico' }],
    ['link', { rel: 'apple-touch-icon', sizes: '180x180', href: '/apple-touch-icon.png' }],
    ['link', { rel: 'manifest', href: '/site.webmanifest' }],
    ['meta', { name: 'apple-mobile-web-app-title', content: 'Howl' }],
    ['meta', { name: 'theme-color', content: '#14213a' }],
    // Open Graph
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:locale', content: 'en' }],
    ['meta', { property: 'og:title', content: 'Howl — Multi-driver Laravel Notifier' }],
    ['meta', { property: 'og:description', content: 'Multi-driver Laravel notifier (Discord, Slack, Telegram) with rich embeds, channel failover, and queue-aware dispatch.' }],
    ['meta', { property: 'og:site_name', content: 'Howl' }],
    ['meta', { property: 'og:url', content: 'https://howl.skaisser.dev/' }],
    ['meta', { property: 'og:image', content: 'https://howl.skaisser.dev/hero-howl.png' }],
    // Twitter Card
    ['meta', { name: 'twitter:card', content: 'summary_large_image' }],
    ['meta', { name: 'twitter:title', content: 'Howl — Multi-driver Laravel Notifier' }],
    ['meta', { name: 'twitter:description', content: 'Discord, Slack, Telegram with rich embeds, channel failover, and queue-aware dispatch.' }],
    ['meta', { name: 'twitter:image', content: 'https://howl.skaisser.dev/hero-howl.png' }],
    // Custom CSS — bump nav logo so the wolf head is actually visible
    ['style', {}, `
      .VPNavBarTitle .title { gap: 10px; }
      .VPNavBarTitle .logo { height: 40px !important; max-width: 140px; }
      @media (max-width: 768px) {
        .VPNavBarTitle .logo { height: 32px !important; max-width: 110px; }
      }
    `],
  ],

  themeConfig: {
    logo: { src: '/logo-howl.svg', alt: 'Howl' },

    nav: [
      {
        text: 'v1.0.0 (latest)',
        items: [
          { text: 'v1.0.0 (latest)', link: '/v1.0.0/guide/' },
          { text: 'next (pre-release)', link: '/next/guide/' },
        ],
      },
      { text: 'GitHub', link: 'https://github.com/skaisser/howl' },
      { text: 'Packagist', link: 'https://packagist.org/packages/skaisser/howl' },
    ],

    sidebar: {
      '/next/': [
        {
          text: 'Prologue',
          items: [
            { text: 'Upgrade Guide', link: '/next/upgrade' },
            { text: 'Release Notes', link: '/next/releases' },
          ],
        },
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/next/guide/' },
            { text: 'Installation', link: '/next/guide/installation' },
            { text: 'Quick Start', link: '/next/guide/quick-start' },
          ],
        },
        {
          text: 'Configuration',
          items: [
            { text: 'Reference', link: '/next/configuration/reference' },
            { text: 'Channel Routing', link: '/next/configuration/channel-routing' },
            { text: 'Failover & Fan-Out', link: '/next/configuration/failover-and-fan-out' },
            { text: 'Rate Limiting', link: '/next/configuration/rate-limiting' },
          ],
        },
        {
          text: 'Drivers',
          items: [
            { text: 'Discord', link: '/next/drivers/discord' },
            { text: 'Slack', link: '/next/drivers/slack' },
            { text: 'Telegram', link: '/next/drivers/telegram' },
          ],
        },
        {
          text: 'Events',
          items: [
            { text: 'HowlEvent Contract', link: '/next/events/contract' },
            { text: 'Built-in Events', link: '/next/events/built-in' },
            { text: 'Custom Events', link: '/next/events/custom' },
          ],
        },
        {
          text: 'Testing',
          items: [
            { text: 'HowlFake', link: '/next/testing/howl-fake' },
            { text: 'Architecture Tests', link: '/next/testing/architecture' },
          ],
        },
        {
          text: 'Extension',
          items: [
            { text: 'Custom Drivers', link: '/next/extension/custom-driver' },
            { text: 'Builder Methods', link: '/next/extension/builder-methods' },
            { text: 'Queue & Rate Limit', link: '/next/extension/queue-and-rate-limit' },
          ],
        },
        {
          text: 'Reference',
          items: [
            { text: 'API Reference', link: '/next/reference/api' },
          ],
        },
      ],

      '/v1.0.0/': [
        {
          text: 'Prologue',
          items: [
            { text: 'Upgrade Guide', link: '/v1.0.0/upgrade' },
            { text: 'Release Notes', link: '/v1.0.0/releases' },
          ],
        },
        {
          text: 'Getting Started',
          items: [
            { text: 'Introduction', link: '/v1.0.0/guide/' },
            { text: 'Installation', link: '/v1.0.0/guide/installation' },
            { text: 'Quick Start', link: '/v1.0.0/guide/quick-start' },
          ],
        },
        {
          text: 'Configuration',
          items: [
            { text: 'Reference', link: '/v1.0.0/configuration/reference' },
            { text: 'Channel Routing', link: '/v1.0.0/configuration/channel-routing' },
            { text: 'Failover & Fan-Out', link: '/v1.0.0/configuration/failover-and-fan-out' },
            { text: 'Rate Limiting', link: '/v1.0.0/configuration/rate-limiting' },
          ],
        },
        {
          text: 'Drivers',
          items: [
            { text: 'Discord', link: '/v1.0.0/drivers/discord' },
            { text: 'Slack', link: '/v1.0.0/drivers/slack' },
            { text: 'Telegram', link: '/v1.0.0/drivers/telegram' },
          ],
        },
        {
          text: 'Events',
          items: [
            { text: 'HowlEvent Contract', link: '/v1.0.0/events/contract' },
            { text: 'Built-in Events', link: '/v1.0.0/events/built-in' },
            { text: 'Custom Events', link: '/v1.0.0/events/custom' },
          ],
        },
        {
          text: 'Testing',
          items: [
            { text: 'HowlFake', link: '/v1.0.0/testing/howl-fake' },
            { text: 'Architecture Tests', link: '/v1.0.0/testing/architecture' },
          ],
        },
        {
          text: 'Extension',
          items: [
            { text: 'Custom Drivers', link: '/v1.0.0/extension/custom-driver' },
            { text: 'Builder Methods', link: '/v1.0.0/extension/builder-methods' },
            { text: 'Queue & Rate Limit', link: '/v1.0.0/extension/queue-and-rate-limit' },
          ],
        },
        {
          text: 'Reference',
          items: [
            { text: 'API Reference', link: '/v1.0.0/reference/api' },
          ],
        },
      ],
    },

    socialLinks: [
      { icon: 'github', link: 'https://github.com/skaisser/howl' },
    ],

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © 2025-present Shirleyson Kaisser | <a href="/llms.txt">llms.txt</a> · <a href="/llms-full.txt">llms-full.txt</a>',
    },

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/skaisser/howl/edit/homolog/docs/:path',
      text: 'Edit this page on GitHub',
    },
  },

  // Custom CSS for howl branding (dark red / burgundy accent)
  vite: {
    css: {
      preprocessorOptions: {},
    },
  },
})
