# OpenDXP MCP Bundle

An AI agent that works on an OpenDXP project guesses twice. It guesses what the installation
contains, and it guesses which of several mechanisms fits the task. Both guesses produce code that
looks right and is wrong.

This bundle runs a [Model Context Protocol](https://modelcontextprotocol.io) server inside the
installation. Its tools answer from the container, the class definitions and the database of the
installation in front of the agent. Documentation describes a version, and these answers describe
this installation. `how_to` answers the second question. It tells which mechanism fits a case, from
decisions that the core and the installed bundles ship.

Built on [`symfony/mcp-bundle`](https://github.com/symfony/mcp-bundle) and the official [`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk).

## Release Plan

| Release | Supported OpenDXP Versions | Supported Symfony Versions | Release Date | Maintained     | Branch |
|---------|----------------------------|----------------------------|--------------|----------------|--------|
| **1.x** | `^1.4`                     | `^7.4`                     | 2026         | Feature Branch | 1.x    |

The server itself is built on `symfony/mcp-bundle`, which is still on `0.x`. Every minor release
of it may change the configuration, so a minor release here may follow it.

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

| Tool             | Answers                                                                                                         |
|------------------|-----------------------------------------------------------------------------------------------------------------|
| `project_map`    | The core version, the PHP version, every installed OpenDXP bundle, and the command that runs the console.       |
| `site_config`    | Website settings with their references resolved, image thumbnails, sites, languages, and where each admin-edited configuration is stored. |
| `model_schema`   | The DataObject classes with their field collections, bricks and stores, and per class every field.              |
| `class_contract` | The public surface of a class, interface or enum: constructor, method signatures, attributes, constants, cases. |
| `docs`           | Searches docs.opendxp.io and answers with the matching pages and an excerpt from each.                          |
| `how_to`         | Which mechanism is the right one for a given case, from the decisions of the core and the installed bundles.    |

Installed bundles contribute their own tools, so the list grows with the installation. Check what
a given installation offers:

```bash
bin/console debug:mcp
```

There is deliberately no tool for services, routes and bundle configuration. `debug:container`,
`debug:router` and `debug:config` already answer those, and a wrapper around them would only fall
behind. What a client cannot know is how to reach the console of an installation it does not run
in, so `project_map` names the command, and the default instructions send the agent there.

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
        Controllers live in src/Controller/<Area>/. Document templates live in templates/content/.
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

## Contributing decisions

Some questions have more than one working answer, and only one of them fits a given case.
`how_to` answers them. This bundle ships the decisions of the core, such as how a class holds a
group of fields or which document type fits. A bundle or a project contributes its own with a
service that implements `DecisionProviderInterface`:

```php
use OpenDxp\Bundle\McpBundle\Guidance\Decision;
use OpenDxp\Bundle\McpBundle\Guidance\DecisionProviderInterface;
use OpenDxp\Bundle\McpBundle\Guidance\Option;
use App\Export\FeedInterface;
use App\Export\PushInterface;

final readonly class DecisionProvider implements DecisionProviderInterface
{
    public function getDecisions(): array
    {
        return [
            new Decision(
                id: 'export_target',
                question: 'How does a partner receive the product export?',
                options: [
                    new Option(name: 'A feed', when: 'The partner fetches the data on its own schedule.', symbol: FeedInterface::class),
                    new Option(name: 'A push', when: 'The partner needs a change the moment it happens.', symbol: PushInterface::class),
                ],
            ),
        ];
    }
}
```

With autoconfiguration the service needs no tag. Write `symbol` as a `::class` constant,
so a renamed class fails static analysis instead of leaving a wrong answer behind.

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

