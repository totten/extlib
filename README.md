# extlib: Extension support library for CiviCRM

* Provide utilities for extensions
* Can be copied into extensions (e.g. via civix)
* Participates in the [pathload](https://github.com/totten/pathload-poc/) scheme for version-resolution

## Build

To make a make single-file representation of this extension (`extlib@X.X.X.phar` or `extlib@X.X.X.php`), run:

```bash
./scripts/build.sh
ls -l dist/
```

## Development

If you an existing extension that uses ``extlib@X.X.X.phar` and want to develop patches, you can simply
add another folder or symlink this codebase. For example:

```bash
git clone https://github.com/totten/extlib ~/src/extlib

cd /path/to/my-extension/mixin/lib
ln -s ~/src/extlib extlib@1.999.0
```

Note the user of version `1.999` ensures that it will take precedence over any other copies of `extlib@1`.
