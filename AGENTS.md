# Project Rules

## Git / Deployment

- NEVER push to `main`, under any circumstances. All pushes go to `develop`.
- NEVER make changes on any server or environment — deploys, database
  writes, running migrations or sync scripts against remote hosts, config
  changes — without the user's explicit permission first.
