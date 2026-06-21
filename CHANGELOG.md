# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Project scaffolding: Composer package `vusys/tetryon`, PHPStan level 9, Pint,
  Rector, Infection, PHPUnit 12/13 config, CI matrix, Dependabot, CodeRabbit,
  and OpenSSF Scorecard.
- `Vusys\Tetryon\Core\Config\Timeouts` and `Viewport` immutable value objects.
- **Firefox WebDriver BiDi driver (v0.1 spike).** Direct, dependency-free
  control of headless Firefox: process launch with a throwaway profile and
  PID-only teardown, a hand-rolled WebSocket transport (`WebSocketClient`,
  RFC 6455), the BiDi protocol layer (`BiDiConnection`) with id correlation,
  event buffering, a structured command trace, and PSR-3 logging. The
  `FirefoxBiDiDriver` exposes navigate / evaluate JS / screenshot / console
  capture. Proven end-to-end against real Firefox in an opt-in Browser suite
  plus a Firefox CI workflow.

[Unreleased]: https://github.com/Vusys/tetryon/commits/master
