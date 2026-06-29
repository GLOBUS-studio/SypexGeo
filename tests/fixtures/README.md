## Database fixtures

Place SypexGeo binary `.dat` files here to enable the integration test suite.

Download databases from: **https://sypexgeo.net/en/download/**

| File | Type | Used by |
|---|---|---|
| `SxGeo.dat` | Country | country-only integration tests |
| `SxGeoCity.dat` | City | city integration + coverage tests |

The directory is git-ignored except for this README. Without fixtures, the
integration suite is skipped automatically — `composer test` will still pass.
