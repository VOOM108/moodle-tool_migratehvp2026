# Migrate mod_hvp to mod_h5pactivity (2026 standalone fork)

This repository contains a **standalone continuation** of the original Moodle plugin:
`moodlehq/moodle-tool_migratehvp2h5p`.

READ DISCLAIMER BEFORE USING IT: https://github.com/VOOM108/moodle-tool_migratehvp2026/issues/1

It migrates activities from `mod_hvp` (Joubel H5P) to Moodle core `mod_h5pactivity`, and extends the original tool with improved migration controls, observability, and recovery workflows.

## Origin and standalone status

- Original project reference: <https://github.com/moodlehq/moodle-tool_migratehvp2h5p>
- This repository (`tool_migratehvp2026`) is maintained independently.
- Versioning in this repository **diverges from upstream** and follows its own release line.

## What is enhanced in this fork

Compared to the original base, this fork includes:

- Pagination fixes in migration listings.
- Filters and extra columns in the migration table.
- Configurable rows-per-page in the admin UI.
- Configurable suffixes when original HVP activities are hidden or kept after migration.
- Options to preserve/migrate original visibility and availability settings.
- More robust checks and detailed per-step logging for successful and failed migrations.
- An admin page to review, retry, or reset failed/incomplete migration rows.

## Usage

There are two ways to run migrations:

- **Web UI**: Site administration -> Migrate content from mod_hvp to mod_h5pactivity (2026 fork)
- **CLI**: `php admin/tool/migratehvp2026/cli/migrate.php --execute`

The tool scans for non-migrated HVP activities and creates corresponding H5P activities.

By default, CLI migrates up to 100 HVP activities per run, keeps originals, and links to Content bank. Run with `--help` to inspect available parameters (limits, keep/delete/hide behavior, content bank handling, filtering, and related options).

Each HVP is migrated once according to the migration map. To re-migrate an item, reset its migration status from the failed/incomplete admin page or remove the mapped target as appropriate for your workflow.

## Dependencies

Minimum requirements:

- Moodle core with `mod_h5pactivity` available and enabled
- `mod_hvp` plugin version `2020020500` or newer

For Content bank integration:

- H5P content type enabled in Content bank
- Content bank repository enabled

## Author

- Andreas Giesen <andreas@108design.com>

## License

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program. If not, see <http://www.gnu.org/licenses/>.
