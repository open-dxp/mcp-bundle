# OpenDXP MCP Bundle

Makes a running OpenDXP installation queryable over the [Model Context Protocol](https://modelcontextprotocol.io),
so an AI agent can ask what this installation actually is instead of guessing from documentation.

Documentation describes a version. This bundle answers from the container, the class definitions
and the database of the installation in front of you, which is the part no written text can keep up
with.

Built on [`symfony/mcp-bundle`](https://github.com/symfony/mcp-bundle) and the official
[`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk).

## Release Plan

| Release | Supported OpenDXP Versions | Supported Symfony Versions | Release Date | Maintained     | Branch |
|---------|----------------------------|----------------------------|--------------|----------------|--------|
| **1.x** | `^1.3`                     | `^7.4`                     | 2026         | Feature Branch | 1.x    |

## Installation

```json
"require-dev": {
    "open-dxp/mcp-bundle": "^1.0"
}
```

Add the bundle to `bundles.php`:

```php
return [
    OpenDxp\Bundle\McpBundle\OpenDxpMcpBundle::class => ['dev' => true, 'test' => true],
];
```

`require-dev` and the environment restriction are deliberate. The tools expose the service
container, the data model and the site configuration. That is what makes them useful during
development and what makes them a liability in production. Enable other environments only with a
reason, and never together with the HTTP transport on a reachable host.

## Tools

| Tool          | Answers                                                                    |
|---------------|----------------------------------------------------------------------------|
| `project_map` | Core and bundle versions, and the API domains the project itself defines.  |

Installed bundles contribute their own tools, so the list grows with the installation. Check what
a given installation offers:

```bash
bin/console debug:mcp
```

## Connecting a client

The server speaks stdio, started by the client:

```bash
bin/console mcp:server opendxp
```

Any MCP client works. In `opencode`, with the application running in DDEV:

```json
{
  "mcp": {
    "opendxp": {
      "type": "local",
      "command": ["ddev", "exec", "-d", "/var/www/html", "php", "bin/console", "mcp:server", "opendxp"],
      "enabled": true
    }
  }
}
```

Without DDEV the command is `["php", "bin/console", "mcp:server", "opendxp"]`.

## Contributing tools from a bundle

A bundle contributes by putting tools in its own `Mcp` namespace. Nothing else is needed, and in
particular no dependency on this bundle, so a bundle that ships without it stays unaffected.

```php
namespace OpenDxp\Bundle\YourBundle\Mcp;

use Mcp\Capability\Attribute\McpTool;

final class YourTool
{
    #[McpTool(name: 'your_bundle_something')]
    public function __invoke(): array
    {
        // ...
    }
}
```

Register the namespace as services, and only when a server is there to expose them:

```php
// YourBundleExtension::prepend()
if ($container->hasExtension('opendxp_mcp')) {
    $loader->load('mcp.yaml');
}
```

The only dependency this adds to your bundle is `mcp/sdk`, for the attribute.

This belongs in `prepend()`, not `load()`. During `load()` Symfony hands each extension a restricted
container that carries no other extensions, so `hasExtension()` is always false there.

If a bundle ships an `Mcp` namespace but never registers it as services, the container build fails
with a pattern that matches no service. That is deliberate: a tool that silently never appears is
harder to notice than a build error.

When the `Mcp` namespace convention does not fit, name the sources explicitly on the bundle class:

```php
use OpenDxp\Bundle\McpBundle\Contribution\McpContributorInterface;

class OpenDxpYourBundle extends AbstractOpenDxpBundle implements McpContributorInterface
{
    public static function getMcpToolSources(): array
    {
        return ['OpenDxp\\Bundle\\YourBundle\\SomewhereElse\\'];
    }
}
```

That path does require a dependency on this bundle, which is why the convention is the default.

## Contributing tools from a project

A project has no bundle class, so it names its own sources in configuration:

```yaml
# config/packages/opendxp_mcp.yaml
opendxp_mcp:
    tools:
        - 'App\Mcp\'
```

## Instructions

Every client receives `instructions` during the handshake. It is the place for the conventions that
hold across the whole project, so they do not have to be repeated in each developer's own
instruction file:

```yaml
opendxp_mcp:
    instructions: |
        API endpoints live in src/Domain/<Context>/, one Action, Query and QueryHandler each.
        Only the handler carries logic.
```

## Writing a tool

Two rules decide whether a tool helps or hurts.

**Answer a question, do not mirror an API.** Six tools shaped like developer questions beat twenty
that wrap `debug:*`. The agent should not have to combine four calls to learn one thing.

**Stay within a budget.** A tool that returns everything fills the context without answering, which
is worse than no tool at all. Roughly 2000 tokens is the ceiling; past that, require a filter or
paginate. Return signatures rather than source, sections rather than files, a table rather than a dump.

## License
**DACHCOM.DIGITAL AG**, Löwenhofstrasse 15, 9424 Rheineck, Schweiz  
[dachcom.com](https://www.dachcom.com), dcdi@dachcom.ch  
Copyright © 2026 DACHCOM.DIGITAL. All rights reserved.  

For licensing details please visit [LICENSE.md](LICENSE.md)

