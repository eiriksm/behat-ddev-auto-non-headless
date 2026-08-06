# behat-ddev-auto-non-headless

A package to make it easier to run non headless locally, without changing the committed config (so it shows up as a diff).

## Installation

Probably you want to download this as a dev package:

```
composer require --dev frontkom/behat-ddev-auto-non-headless
```

## Usage

Add something like this to your `behat.yml(.dist)` file:

```
  extensions:
    frontkom\BehatAutoDdevNonHeadless\DisableHeadlessExtension: ~
```

Inside DDEV (`IS_DDEV_PROJECT=true`) the extension then drops every `--headless`
switch from the Selenium2 session, so you can watch the run over noVNC. Outside
DDEV it does nothing, so CI is unaffected.

Set `BEHAT_HEADLESS=1` to keep the browser headless anyway — useful for long runs
and for reproducing the CI setup locally:

```
ddev exec "BEHAT_HEADLESS=1 vendor/bin/behat"
```

### Supported capability shapes

Mink takes Chrome switches in more than one shape, and all of them are handled:

```yaml
selenium2:
  capabilities:
    chrome:
      switches: ["--headless"]              # legacy
    extra_capabilities:
      goog:chromeOptions:
        args: ["--headless=new"]            # W3C
```

`goog:chromeOptions.args` without `extra_capabilities` works too. Sessions are
matched by driver class, not by session name.

## Licence 

MIT
