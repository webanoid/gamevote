# GameVote documentation

The [project README](../readme.md) covers running the app and the main decisions. These pages go
deeper. The diagrams are [Mermaid](https://mermaid.js.org/), which GitHub renders inline.

| Page                                  | What it covers                                                               |
| ------------------------------------- | ---------------------------------------------------------------------------- |
| [Architecture](architecture.md)       | How a request flows from the browser to the API, and how the code is layered |
| [Data model](data-model.md)           | What lives in SQLite versus the external API, and why                        |
| [The daily rule](daily-rule.md)       | One vote or one new game per day: enforcement, refunds, timezones            |
| [API integration](api-integration.md) | The client, the API's quirks, key management and recovery                    |
| [Frontend](frontend.md)               | Page structure, the meeple bar chart, re-sort animation, performance         |
| [Deployment](deployment.md)           | Docker stacks, environment variables, operating the app                      |
| [Testing](testing.md)                 | Test strategy, the fake API, what each suite covers                          |
| [Decisions](decisions.md)             | A log of the significant choices and the alternatives considered             |
