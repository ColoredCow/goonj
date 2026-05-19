# Coding configuration upgrades

If your Inlay type’s configuration changes you will need to handle upgrading carefully. This page is a top-level overview of this process.

When a `InlaySomething` instance is loaded:

1. When an inlay is instantiated, the stored config array is passed to the type's `setConfig()` method.
2. the `InlaySomething::CONFIG_VERSION` is checked against the stored `$config['version']` key. It’s possible that neither of these exist. But if they aren't exactly equal, then the config array is passed through the type’s `migrateConfig()` method to handle this. This method is responsible for altering the non-persisted config array for any version differences.

3. the config array is then possibly altered by `validateConfig` in _coerce_ mode.
4. `validateConfig()` by default just ensures that the only top-level keys in `$config` are those defined by `InlaySomething::$defaultConfig` and that any missing ones are populated from defaults. But your type may implement `getConfigSchema()` in which case there's the possibility that errors arise leading to the instance being marked as **broken** and therefore made inactive.
5. The config then becomes the inlay's usable config, which will become used in the bundle file as usual. Editing an instance through the UI will cause this to run and if you save it, then the migration becomes persisted, so on upgrades, admins should check carefully for any changes (and re-test).

See the docblock comments against these methods.

## Upgrader class

The above aims to protect live inlay instances from broken config before the upgrader runs. Hopefully a small time window, but on busy sites it could be important.

When upgrading you will want to deliberately persist your configuration migrations.

The typical pattern for this is:

1. Loop and instantiate the instances of your type. This will trigger the `migrateConfig()`
2. Optionally put more code in your `upgrade_000n()`.
3. Do a final `$inlaySomething->validateConfig($config)`, then save the output of `$inlaySomething->getConfig()` to the Inlay entity.

## migrateConfig()

You override this method in your class, but probably should end with `return parent::migrateConfig($config);`. Currently, all that does is set the current `InlaySomething::CONFIG_VERSION` in the config array, but keep an eye on it!

## getConfigSchema validateConfig()

This uses `\Civi\Inlay\ArraySchema` class with which you compare your `$config` array against an array structure defining what's acceptable. There’s fairly extensive docblocks in that class and also see `ArraySchemaTest` for further info.

This is meant for complex situations, e.g. InlayPay's config is very complex.
