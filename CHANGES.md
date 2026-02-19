# VERSIONS HISTORY

## Changes in version 0.9.0 (2026021802) - Category-aware filtering

- Add category filter to the migration list page.
- Make category, course and content-type filters work together more intuitively.
- Add dynamic dropdown behavior so selecting a category narrows the available courses instantly.
- Keep selected course/category combinations consistent when URL parameters are stale or mismatched.
- Bump plugin release to 0.9 and version to 2026021802.

## Changes in version 0.7.0 (20260218) - Standalone 2026 fork

- Standalone continuation of the original `moodlehq/moodle-tool_migratehvp2h5p` plugin.
- Versioning diverges from upstream and now follows this repository release line.
- Fix pagination issues in migration listings.
- Add filters and additional columns to improve migration table usability.
- Add selectable rows-per-page in the migration UI.
- Add configurable suffixes for migrated source activities when they are hidden or kept.
- Add options to preserve/migrate visibility and availability settings.
- Improve robustness checks and per-step logging for successful and failed migrations.
- Add an admin page to review and resolve failed or incomplete migrations.

## Changes in version 0.2.0 (20250930) - I'm a new me
- Add Moodle 5.0 and 5.1 support
- For Moodle 4.5 downwards, version 0.1.7 is the latest stable
- Fix coding style
- Bump GHA environments

## Changes in version 0.1.7 (20250120) - I'm still alive

- Add Moodle 4.4 and 4.5 support
- Replace deprecated calls
- Improve markdown and fix coding style

## Changes in version 0.1.6 (20240111) - RIP master

- Move from master to main (#51)
- Bump GHA environments

## Changes in version 0.1.5 (20221129) - I'm now stable

- Include author, if possible, when migrating to content bank (#46)
- Fix SQL limit with Oracle (#34)

Thanks to Jonathan Harker from Catalyst and all the contributors who have created issues, fixes and improvements.

## Changes in version 0.1.4 (20210204) - Let's make it better

- Copy completion information related to grades too (#27)
- Check if contentbank repository is enabled or not (#30)
- Check required plugins are enabled (#32)
- Create manually CB file when it is null (#25)
- Other fixes and improvements:
  - Add GitHub action support (#22)

Thanks to Jordan, Ramon Ovelar, Adrian Perez and all the contributors who have created issues, fixes and improvements.

## Changes in version 0.1.3 (2020121100) - Hurray project week

- Let admins copy files to content bank too (#11)
- Migrate depending on a subset of content-types (#19)
- Allow async module deletes (#14)
- Other fixes and improvements:
  - Set grade type to None when maxgrade is 0 (#7)
  - Rawscore and maxscore can not be null (#8)

Thanks to Eric Merrill, GaRaOne and Alexander Bias and all the contributors who have created issues, fixes and improvements.

## Changes in version 0.1.2 (2020062400) - Welcome Beta

- Some migration errors fixes:
  - Use "enable skipping" from the competency API to avoid migration failed error (#1)
  - Set contextid properly in event trigger (#3)
  - Replace wrong table name and hardcoded prefix
