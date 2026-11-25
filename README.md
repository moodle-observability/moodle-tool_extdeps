<img src="docs/logo/logo.svg" alt="moodle-tool_extdeps logo" width="96" align="right" />

# moodle-tool_extdeps

[![Moodle Plugin CI](https://github.com/moodle-observability/moodle-tool_extdeps/actions/workflows/ci.yml/badge.svg)](https://github.com/moodle-observability/moodle-tool_extdeps/actions/workflows/ci.yml)
[![Documentation](https://github.com/moodle-observability/moodle-tool_extdeps/actions/workflows/docs.yml/badge.svg)](https://github.com/moodle-observability/moodle-tool_extdeps/actions/workflows/docs.yml)

Monitor of the external dependencies of the Moodle courses (`tool_extdeps`). Finds the videos, packages and other external content used by every course (YouTube, Vimeo, eduplay and SCORM), checks them periodically without overloading the providers, and records when something becomes private, removed or blocked. It never changes the course content.

**Status:** 0.1.3 (alpha). Download it from the [releases](https://github.com/moodle-observability/moodle-tool_extdeps/releases).

Documentation (English and Brazilian Portuguese): https://moodle-observability.github.io/moodle-tool_extdeps/

Requirements: Moodle 4.5 or 5.3 (LTS).

Quick start: install the plugin in `admin/tool/extdeps` (`public/admin/tool/extdeps` on Moodle 5.1 and later) and upgrade Moodle. Then open *Site administration > Plugins > Admin tools > External dependencies*. The reports are in [moodle-report_extdeps](https://github.com/moodle-observability/moodle-report_extdeps).

Part of [Moodle Observability](https://github.com/moodle-observability). Contributions: see the [contributing guide](https://github.com/moodle-observability/.github/blob/main/CONTRIBUTING.md).

License: [GPL-3.0](LICENSE).
