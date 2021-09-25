# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

* Add optional operation key `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::upsert`, `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::delete` for easier task recognition
* Add optional context parameter to `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::flush` allowing an override of the used modified context

### Changed

* Improve memory usage and first call wall time of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\ExistingIdentifierCache::getProductMediaId` by dropping id cache warmup
* Throw `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Exception\DuplicateSyncOperationKeyPreventionException` with code `1632595313` when `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::upsert`, `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::delete` and `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::push` have a duplicate mismatch
* Change return value of `\Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer::flush` from `self` to `\Shopware\Core\Framework\Api\Sync\SyncResult` to access pure sync api result
