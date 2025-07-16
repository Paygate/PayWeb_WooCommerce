# Changelog

## [1.4.0](https://github.com/Payfast/payfast-common/releases/tag/v1.4.0)

### Added

- Improve Aggregator **createTransaction()** to accept **$returnForm** parameter to make it easier to debug custom
  integration forms.

## [1.3.1](https://github.com/Payfast/payfast-common/releases/tag/v1.3.1)

### Fixed

- Fix Error 411 on Aggregator for some servers, where POST requests require a Content-length header.

## [1.3.0](https://github.com/Payfast/payfast-common/releases/tag/v1.3.0)

### Added

- Improve Aggregator **placeRequest()** to accept **$returnCurlRequest** parameter to make it easier to debug API calls.

## [1.2.2](https://github.com/Payfast/payfast-common/releases/tag/v1.2.2)

### Improved

- Amend **placeRequest()** to accept **timestamp** and **version** if sent in **$body**.
- Empty **$body** if **action** is set. This is used for **Query/Retrieve Refund**.

### Removed

- Obsolete sample code.

## [1.2.1](https://github.com/Payfast/payfast-common/releases/tag/v1.2.1)

### Changed

- Refactored common API methods to use instance-based (non-static) implementations for improved
  flexibility and testability.

### Fixed

- Corrected the URL used in the Refund method to ensure accurate API requests.

## [1.2.0](https://github.com/Payfast/payfast-common/releases/tag/v1.2.0)

### Added

- Introduce new **Gateway** library to streamline and enhance request processing.

### Improved

- Rename namespaces and classes for better clarity and alignment with industry standards.

### Breaking Changes

- **Namespace Changes**: Updated namespaces for several core classes. This change requires consumers to update their
  imports to match the new namespace structure.
- **Class Renames**: Some classes have been renamed for consistency. Existing references to these classes will need to
  be updated.

## [1.1.0](https://github.com/Payfast/payfast-common/releases/tag/v1.1.0)

### Breaking

- Refactor static to instance methods.
- Convert constants to object properties.

## [1.0.2](https://github.com/Payfast/payfast-common/releases/tag/v1.0.2)

### Added

- Samples.

### Changed

- Code quality improvements.

## [1.0.1](https://github.com/Payfast/payfast-common/releases/tag/v1.0.1)

### Fixed

- Catch for write permission issues.

## [1.0.0](https://github.com/Payfast/payfast-common/releases/tag/v1.0.0)

### Added

- Initial release.
