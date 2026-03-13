# Messenger Logging Bundle

Symfony bundle for Messenger-Lifecycle-Logging, suitable for monitoring.

## Installation

```bash
composer require ckrack/messenger-logging-bundle
```

If you are not using Symfony Flex, register the bundle manually in
`config/bundles.php`.

The bundle targets Symfony `6.4`, `7.4`, and `8.0`.

## Configuration

```yaml
c10k_messenger_logging:
  enabled: true
  log_channel: messenger
  log_levels:
    queued: info
    received: info
    handled: info
    failed: error
    retried: warning
    skipped: warning
```

Alle PSR-3-Levels sind erlaubt. Wenn Failure-Logs in einer retry-lastigen
Umgebung zu laut sind, kannst du `failed: info` setzen.

Wenn `log_channel` gesetzt ist, werden nur die von diesem Bundle erzeugten
Logs auf diesen Monolog-Channel gelegt. Andere Projekt-Logs bleiben davon
unberührt, solange sie nicht separat auf denselben Channel konfiguriert werden.
Ohne `log_channel` bleibt das bisherige Default-Logger-Verhalten unverändert.

Das Bundle hängt sich an Messenger-Events und sorgt dafür, dass eine Message
beim Queueing eine UUIDv7 erhält. Dieselbe UUID taucht dann in den Logs für
Queueing, Consume, Success, Failure und Retry wieder auf, zusammen mit der
Message-Klasse und dem vollständigen Stamp-Kontext.

Wenn die installierte Messenger-Version `WorkerMessageSkipEvent` unterstützt,
werden auch übersprungene Messages geloggt.

## Local development

- Docker with Compose V2
- pre-commit
- GNU Make

```bash
make setup
make check
make fix
```

`make setup` installs Composer dependencies and both the `pre-commit` and
`pre-push` hooks.
