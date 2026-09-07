# OpenDXP MCP Bundle

Makes a running OpenDXP installation queryable over the [Model Context Protocol](https://modelcontextprotocol.io),
so an AI agent can ask what this installation actually is instead of guessing from documentation.

Documentation describes a version. This bundle answers from the container, the class definitions
and the database of the installation in front of you, which is the part no written text can keep up
with.

Built on [`symfony/mcp-bundle`](https://github.com/symfony/mcp-bundle) and the official [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk).

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

| Tool             | Answers                                                                                                        |
|------------------|----------------------------------------------------------------------------------------------------------------|
| `project_map`    | The core version, the PHP version and every installed OpenDXP bundle with its version.                         |
| `site_config`    | Website settings with their references resolved, image thumbnails with their transformation, sites, languages. |
| `model_schema`   | The DataObject classes, and per class every field with its type, localization and relation targets.            |
| `class_contract` | The public surface of a class or interface: constructor, method signatures, attributes, constants.             |
| `docs`           | Searches docs.opendxp.io and answers with the matching pages and an excerpt from each.                         |

Installed bundles contribute their own tools, so the list grows with the installation. Check what
a given installation offers:

```bash
bin/console debug:mcp
```

`docs` is the one tool that leaves the machine. It queries the search endpoint of the
documentation site, and nothing else is sent anywhere. Point it elsewhere or turn it off:

```yaml
# config/packages/opendxp_mcp.yaml
opendxp_mcp:
    docs_url: null
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

## Instructions

Every client receives `instructions` during the handshake, which is the place for conventions that
hold across the whole project:

```yaml
# config/packages/opendxp_mcp.yaml
opendxp_mcp:
    instructions: |
        API endpoints live in src/Domain/<Context>/, one Action, Query and QueryHandler each.
        Only the handler carries logic.
```

## Adding your own tools

A tool is a class with an `#[McpTool]` attribute. Register it and name its namespace in the server:

```yaml
# config/packages/opendxp_mcp.yaml
services:
    App\Mcp\:
        resource: '../../src/Mcp'

mcp:
    servers:
        opendxp:
            registry:
                tools:
                    - 'App\Mcp\'
```


## Writing a good tool

Write tools that answer a question, not tools that mirror an API. Ask yourself what a developer
wants to know. Six of those beat twenty wrappers around `debug:*`.

Keep the answer short. Signatures, not source. The relevant section, not the whole file. An agent
reads with a limited context, and a tool that returns everything fills it up without answering
anything.

## License
**DACHCOM.DIGITAL AG**, Löwenhofstrasse 15, 9424 Rheineck, Schweiz  
[dachcom.com](https://www.dachcom.com), dcdi@dachcom.ch  
Copyright © 2026 DACHCOM.DIGITAL. All rights reserved.  

For licensing details please visit [LICENSE.md](LICENSE.md)

