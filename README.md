<p align="center">
  <img src="docs/logo.png" alt="Messenger Logging Bundle logo" width="240">
</p>

# Messenger Logging Bundle

Symfony bundle for Messenger lifecycle logging, suitable for monitoring.

## Overview

This bundle subscribes to Symfony Messenger lifecycle events and emits
structured logs that are easy to correlate in monitoring and debugging tools.

### What It Adds

- A UUIDv7 is assigned when a message is queued.
- The same UUID is reused across queueing, receiving, handling, failures, and retries.
- Each log entry includes the message class and a normalized `stamps` array.
- Transport metadata such as receiver names, retry counts, and failure-transport
  details are included when available.

### Supported Lifecycle Events

The bundle logs queueing, receiving, success, failure, and retry events. If the
installed Messenger version supports `WorkerMessageSkipEvent`, skipped messages
are logged as well.

## Installation

### Package Installation

```bash
composer require ckrack/messenger-logging-bundle
```

### Bundle Registration

If you are not using Symfony Flex, register the bundle manually in
`config/bundles.php`.

### Supported Versions

The bundle targets Symfony `6.4`, `7.4`, and `8.0`.

## Configuration

### Basic Configuration

```yaml
ckrack_messenger_logging:
  enabled: true
  log_channel: messenger
  log_levels:
    queued: info
    received: info
    handled: info
    failed: error
    retried: warning
    skipped: warning
  stamp_normalizers: {}
```

### Log Levels

All PSR-3 log levels are supported. If failure logs are too noisy in a
retry-heavy environment, you can set `failed: info`.

### Dedicated Log Channel

If `log_channel` is set, only the logs emitted by this bundle are sent to that
Monolog channel. Other project logs remain unaffected unless they are
explicitly configured to use the same channel. Without `log_channel`, the
default logger behavior remains unchanged.

## Stamp Normalization

### Default Behavior

The bundle normalizes a safe subset of Messenger stamp data and includes it in
the `stamps` field of each log entry. Built-in normalizers cover
`BusNameStamp`, `DelayStamp`, `HandledStamp`, `RedeliveryStamp`,
`RouterContextStamp`, `SentStamp`, `TransportNamesStamp`, and
`ValidationStamp`.

Unknown stamps are still listed by class name, but their `context` remains
empty unless a normalizer is registered for them. This avoids reflecting every
public getter on every stamp, which can expose sensitive data or large payloads
such as handler results.

### Custom Normalizers

Custom normalizers are discovered automatically when they implement
`C10k\MessengerLoggingBundle\Logging\StampNormalizerInterface` and are
registered as autoconfigured services. The bundle tags them with
`ckrack_messenger_logging.stamp_normalizer` and maps them by supported stamp
class.

You can also wire an explicit `StampClass -> NormalizerClass` mapping via
configuration, which is useful for overrides:

```yaml
ckrack_messenger_logging:
  stamp_normalizers:
    App\Messenger\CustomStamp: App\Messenger\Logging\CustomStampNormalizer
```

## Logged Lifecycle

### Example Message Flow

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

### Additional Context Fields

Each entry also contains the normalized `stamps` array. Depending on the
envelope state, the bundle may also include fields such as
`transport_message_id`, `from_failed_transport`, and
`failed_transport_original_receiver_name`.

## Local development

### Prerequisites

- Docker with Compose V2
- pre-commit
- GNU Make

### Common Commands

```bash
make setup
make check
make fix
```

`make setup` installs Composer dependencies and both the `pre-commit` and
`pre-push` hooks.
