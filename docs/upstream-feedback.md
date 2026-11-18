# Feedback for Symfony AI and the PHP MCP SDK

Found while building Workflow Gate on `symfony/ai-*` 1.0.0-BETA1, `symfony/mcp-bundle` 1.0.0-BETA1 and `mcp/sdk` 0.9.0, and checked against the `main` branch of both projects on 2026-11-18.

## Reported

### Symfony AI: an agent sends the tools it read before the run with every model call

`Symfony\AI\Agent\Execution\Runner::run()` calls `exposeTools()` once and reuses the options for every model call of the run. A toolbox whose tools change while the agent works (here: the order was paid, so `ship_order` exists now) is only read again on the next `Agent::call()`.

Workaround in this project: [`LiveToolsPlatform`](../lib/Agent/LiveToolsPlatform.php), a platform decorator that sets `$options['tools']` from the toolbox on every `invoke()`.

### PHP MCP SDK: one change notification per registry call

`Registry::registerTool()` and `unregisterTool()` each dispatch a `ToolListChangedEvent`. Replacing `cancel_order` by `ship_order` and `refund_order` sends three `notifications/tools/list_changed` in a row. A way to group several registry changes into one announcement would help servers with dynamic tools.

## Already known upstream

- **No change notification for clients before 2026-07-28.** `initialize` answers without `listChanged`, so a handshake-era client such as MCP Inspector 0.15 sees new tools only when it lists again. This is a decision, see modelcontextprotocol/php-sdk#593.
- **The `cache` notification bus needs `psr/simple-cache`.** On 1.0.0-BETA1 the first use fails with `Interface "Psr\SimpleCache\CacheInterface" not found`. Reported as symfony/ai#2826 and fixed on `main`, where the bundle requires the package.
