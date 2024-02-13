# extlib: Extension support library for CiviCRM

* Provide utilities for extensions
* Can be copied into extensions (e.g. via civix)
* Participates in the [pathload](https://github.com/totten/pathload-poc/) scheme for version-resolution

## Development

If you have an existing extension that uses `extlib@X.X.X.phar` and want to develop updates, then
simply clone this repo and assign a fake version number (`1.999.0`). You may optionally use symlinks.

I'm currently using the symlink approach. This is because I *think about* `extlib` as a separate project
which is shared by many extensions.

```bash
## Make a standalone copy of the library
mkdir ~/src
git clone https://github.com/civicrm/extlib ~/src/extlib

## Include the library in an extension
cd /path/to/my-extension/mixin/lib
ln -s ~/src/extlib extlib@1.999.0
```

However, it would also work to make a direct clone:

```bash
cd /path/to/my-extension/mixin/lib
git clone https://github.com/civicrm/extlib extlib@1.999.0
```

## Build

To build a copy of this library for redistribution, run:

```bash
./scripts/build.sh X.X.X
ls -l dist/
```

You should see two files like:

```
dist/extlib@X.X.X.phar
dist/extlib@X.X.X.php
```
