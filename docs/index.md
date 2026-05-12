---
layout: home

hero:
  name: Howl
  text: Multi-driver Laravel Notifier
  tagline: Discord, Slack, and Telegram alerts with rich embeds, channel failover, and queue-aware dispatch. PHP 8.3+, Laravel 12 & 13.
  image:
    src: /hero-logo.png
    alt: Howl
  actions:
    - theme: brand
      text: Get Started
      link: /v1.0.0/guide/
    - theme: alt
      text: View on GitHub
      link: https://github.com/skaisser/howl

features:
  - icon: 🐺
    title: Driver-Agnostic API
    details: One unified API for Discord, Slack, and Telegram. Switch drivers per-call without changing application code.
  - icon: 🔀
    title: Channel Failover & Fan-Out
    details: Route notifications to backup channels automatically on failure, or fan out to multiple channels simultaneously.
  - icon: ⚡
    title: Queue-Aware Dispatch
    details: Async job dispatch with retry logic, exponential backoff, and opt-in Redis rate limiting per driver.
  - icon: 🧪
    title: HowlFake Testing
    details: First-class test support with HowlFake assertions—assert sent events, channels, drivers, and payloads without real HTTP calls.
  - icon: 📨
    title: Rich Embeds
    details: Full Block Kit for Slack, Discord embeds, and Telegram HTML. Fields, code blocks, buttons, attachments, and mentions across all drivers.
  - icon: 🎯
    title: Built-in Event Templates
    details: Seven production-ready event templates covering exceptions, deployments, audits, cron heartbeats, job retries, and more.
---
