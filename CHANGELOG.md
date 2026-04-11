# Changelog

All notable changes to this project will be documented in this file.

## [1.0.8] - 2026-03-16

- Dedicated `NewsController`, Draft/Published status, and Author tracking.
- Automatic Sparkle release metadata synchronization and version extraction.
- Overhauled Products and News management interfaces with modern styling and improved layout.
- Resolved "headers already sent" error in RSS/XML feeds by optimizing `VisitListener`.
- Restricted feeds to Published content and excluded feeds from visitor tracking.
- Centered footer branding, fixed header search alignment, and updated login page aesthetics.
- Added `SecurityHeaderSubscriber` and initial `GitlabService` integration.

## [1.0.7] - 2026-03-12

- Removed Passkey module
- Added ftp analysis module to ProjT Launcher rss feed
- Removed Dark mode
- Updated some pages to remove github references
- Fixed some issues

## [1.0.6] - 2026-03-06

- Added Trademark Page
- Removed Mailing Lists Page
- Removed Gitlab Bridge
- Fixed minimal issues

## [1.0.5] - 2026-02-22

- Fixed Submodule paths

## [1.0.4] - 2026-02-22

- Fixed Gitlab Ci

## [1.0.3] - 2026-02-22

- Fixed Gitlab Ci
- Added SPDX header to Project Tick Control Command
- Updated Submodule refs
- Added missing license files

## [1.0.2] - 2026-02-22

- Fixed Gitlab Ci

## [1.0.1] - 2026-02-22

- Added Gitlab Ci
- Added RPM Build

## [1.0.0] - 2026-02-22

- Initial Production Release.
- Implemented Module-based feature toggles.
- Advanced licensing and DNS-based infrastructure protection.
- Integrated GitHub Bot & GitLab Bridge.
- Dynamic CLA signing system with official PDF generation.
- **Refactored module services and controllers into a submodule architecture.**
- Updated namespaces and service configurations for production-ready modularity.
