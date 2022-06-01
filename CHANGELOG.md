# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to a variation of [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
The version numbers are structured like `GENERATION.MAJOR.MINOR.PATCH`:

* `GENERATION` version when concepts and APIs are abandoned, but brand and project name stay the same,
* `MAJOR` version when you make incompatible API changes and provide an upgrade path,
* `MINOR` version when you add functionality in a backwards compatible manner, and
* `PATCH` version when you make backwards compatible bug fixes.

## [Unreleased]

### Added

### Changed

- Change address splitting on customer creation in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver\CustomerReceiver::getAddress` to satisfy the shopware standard address representation.

### Deprecated

### Removed

### Fixed

### Security

## [0.9.0.0] - 2022-04-05

### Added

- Interpret `\Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product::getMedias` to receive medias on products

### Changed

- Use `\Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media::getFile` for transferring instead of `\Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media::getNormalizedStream`

### Deprecated

- Deprecate `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Portal` as renamed to `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\LocalShopwarePlatformPortal`

### Removed

- Remove support for `shopware/core: >=6.2.1 <6.4`

### Fixed

- Product medias unpacked by `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker` have position by appearance in the product entity

## [0.8.2] - 2022-02-09

### Fixed

- Fix function call on null in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter\OrderEmitter`.
- Fix compatibility with `shopware/core:^6.4` by supporting `\Shopware\Core\System\Currency\CurrencyEntity::setItemRounding` if it exists.

## [0.8.1] - 2021-12-07

### Fixed

- Fetch VAT-ID from customer instead of address in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer\CustomerPacker::pack`

## [0.8.0] - 2021-11-22

### Added

- Add `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher` to centralize translation handling of incoming locale matching
- Add `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` to centralize translations payload generation of any `\Heptacom\HeptaConnect\Dataset\Base\Translatable\Contract\TranslatableInterface`
- Add log message code `1637342440` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher::match` when a locale code is tested against a Shopware language
- Add log message code `1637342441` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher::match` when a locale code could not be matched
- Add log message code `1637342442` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher::match` when a locale code could be matched to a unique Shopware language
- Add log message code `1637342443` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher::match` when a locale code could be matched against multiple other Shopware languages
- Add log message code `1637344184` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker::unpack` when a translated value is tried to be applied but the language code could not be mapped to a Shopware language
- Add `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\CategoryUnpacker` to unpack category data into Shopware API payload
- Add `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\CustomerGroupUnpacker` to unpack customer group data into Shopware API payload
- Use `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\MediaUnpacker` to support translations
- Use `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ManufacturerUnpacker` to support translations
- Use `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyValueUnpacker` to support translations
- Use `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyGroupUnpacker` to support translations
- Add `\Shopware\Core\System\Language\LanguageLoaderInterface` to portal node container
- Add compatibility in code for `ramsey/uuid: ^4` and therefore changed composer requirement to `ramsey/uuid: ^3.5 || ^4`

### Changed

- Move `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver\CategoryReceiver` into short notation
- Move `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver\CustomerGroupReceiver` into short notation
- Change default value for configuration for `dal_indexing_mode` from `none` to `queue`
- Use `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\UnitUnpacker` instead of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator`
- Use `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker` instead of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache` directly
- Use `\Shopware\Core\System\Language\LanguageLoaderInterface` in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache` instead of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess`
- Remove `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache` dependency from `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ManufacturerUnpacker`

### Fixed

- Use fallback translations values for default language for the keys name and description in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker::unpackTranslations`

### Removed

- Remove `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator` in favour of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker` and `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache`

## [0.7.0] - 2021-09-25

### Added

- Add optional operation key `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::upsert`, `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::delete` for easier task recognition
- Add optional context parameter to `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::flush` allowing an override of the used modified context
- Extract locale code caching from `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator` into new service `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache`
- New protected method `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker::unpackTranslations` adds support for translated product content. By default `name` and `description` is supported
- Add product property assignments in return value of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker::unpack`
- Add cleanup of previously imported product properties in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver\ProductReceiver`

### Changed

- Improve memory usage and first call wall time of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache::getProductMediaId` by dropping id cache warmup
- Throw `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Exception\DuplicateSyncOperationKeyPreventionException` with code `1632595313` when `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::upsert`, `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::delete` and `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::push` have a duplicate mismatch
- Change return value of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::flush` from `self` to `\Shopware\Core\Framework\Api\Sync\SyncResult` to access pure sync api result
- `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductPriceUnpacker::unpack` now expects a price collection and a product identifier to generate product price rules more efficiently

### Removed

- Remove unused `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator::getIngredientTranslation`
- In favour of translations `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker::unpack` no longer adds `name` and `description` in the payload root
- Remove `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductUnpacker::unpackPrices` due to complete extraction into `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\ProductPriceUnpacker::unpack`

### Fixed

- Change payload key that references property groups in `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyValueUnpacker::unpack` to fix reception of `\Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue`
