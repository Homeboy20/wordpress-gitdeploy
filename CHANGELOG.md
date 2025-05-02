# Changelog

All notable changes to this project will be documented in this file.

## [2.1.0] - 2023-08-10

### Added
- Full private repository support with authenticated API access
- New Connect Repository tab for direct repository URL connection
- Auto-detection of repository type (plugin or theme)
- Added deploy immediately option when connecting repositories
- Auto-update enablement during repository connection

### Fixed
- Fixed PHP fatal error when handling array responses from GitHub API
- Fixed "Cannot use object of type stdClass as array" error in deploy.php template
- Improved error handling for private repository downloads
- Better type handling between arrays and objects throughout the codebase

### Changed
- Updated GitHub API integration to use authentication for private repositories
- Improved repository tracking sync between options table and database
- Enhanced README with better instructions for private repositories
- Updated token scope requirements for private repository access

## [2.0.0] - 2023-07-15

### Added
- Repository tracking feature for automatic updates
- Database storage for deployed repositories
- Auto-updater that checks hourly for repository changes
- Repository connection status monitoring
- Repository management interface

### Changed
- Completely redesigned admin interface
- Improved error handling and user feedback
- Enhanced GitHub API integration

### Fixed
- Various PHP errors and warnings
- Better error reporting for deployment failures

## [1.0.0] - 2023-06-01

### Added
- Initial plugin release
- Basic GitHub repository deployment
- Support for plugins and themes
- Branch and tag selection
- Update capabilities for existing installations 