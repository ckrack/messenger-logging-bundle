# Messenger Logging Bundle

Symfony bundle for Messenger lifecycle logging, suitable for monitoring.

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
  stamp_normalizers: { }
```

All PSR-3 log levels are supported. If failure logs are too noisy in a
retry-heavy environment, you can set `failed: info`.

If `log_channel` is set, only the logs emitted by this bundle are sent to that
Monolog channel. Other project logs remain unaffected unless they are
explicitly configured to use the same channel. Without `log_channel`, the
default logger behavior remains unchanged.

The bundle subscribes to Messenger events and ensures that each message
receives a UUIDv7 when it is queued. The same UUID then appears in the logs for
queueing, receiving, success, failure, and retry, together with the message
class and a normalized `stamps` array.

Stamp normalization is explicit. The bundle ships dedicated normalizers for a
safe subset of Messenger stamps such as `BusNameStamp`, `DelayStamp`,
`HandledStamp`, `RedeliveryStamp`, `RouterContextStamp`, `SentStamp`,
`TransportNamesStamp`, and `ValidationStamp`. Unknown stamps are still listed
by class name, but their `context` remains empty unless a normalizer is
registered for them. This avoids reflecting every public getter on every stamp,
which can expose sensitive data or large payloads such as handler results.

Custom normalizers are discovered automatically when they implement
`C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface` and are
registered as autoconfigured services. The bundle tags them with
`c10k_messenger_logging.stamp_normalizer` and maps them by supported stamp
class.

You can also wire an explicit `StampClass -> NormalizerClass` mapping via
configuration, which is useful for overrides:

```yaml
c10k_messenger_logging:
  stamp_normalizers:
    App\Messenger\CustomStamp: App\Messenger\Logging\CustomStampNormalizer
```

If the installed Messenger version supports `WorkerMessageSkipEvent`, skipped
messages are logged as well.

## Example Lifecycle

The example below shows how a single message can appear in the logs when it is
queued, fails once, is retried, and is eventually handled successfully. The
same `uuid` is present in every log entry, which makes correlation
straightforward.

<table>
  <thead>
    <tr>
      <th>event</th>
      <th>context</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><code>message queued</code></td>
      <td><pre>uuid:          018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc
message_class: App\Message\SyncInvoice
sender_names:  ["async"]
retry_count:   0</pre></td>
    </tr>
    <tr>
      <td><code>message received (attempt 1)</code></td>
      <td><pre>uuid:                     018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc
message_class:            App\Message\SyncInvoice
receiver_name:            async
received_transport_names: ["async"]
retry_count:              0</pre></td>
    </tr>
    <tr>
      <td><code>message failed (will retry)</code></td>
      <td><pre>uuid:                     018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc
message_class:            App\Message\SyncInvoice
receiver_name:            async
received_transport_names: ["async"]
retry_count:              0
will_retry:               true
exception_class:          RuntimeException
exception_message:        Temporary upstream timeout</pre></td>
    </tr>
    <tr>
      <td><code>message scheduled for retry</code></td>
      <td><pre>uuid:          018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc
message_class: App\Message\SyncInvoice
receiver_name: async
retry_count:   1</pre></td>
    </tr>
    <tr>
      <td><code>message received (attempt 2)</code></td>
      <td><pre>uuid:                     018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc
message_class:            App\Message\SyncInvoice
receiver_name:            async
received_transport_names: ["async"]
retry_count:              1</pre></td>
    </tr>
    <tr>
      <td><code>message handled</code></td>
      <td><pre>uuid:                     018f0c0c-6f9e-7eec-bfc3-6f8d3426f5dc
message_class:            App\Message\SyncInvoice
receiver_name:            async
received_transport_names: ["async"]
retry_count:              1</pre></td>
    </tr>
  </tbody>
</table>

Each entry also contains the normalized `stamps` array, plus fields such as
`transport_message_id`, `from_failed_transport`, and
`failed_transport_original_receiver_name` when those details are available.

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
