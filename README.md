# EZ DELIVERY

![PHP Version](https://img.shields.io/badge/php-%5E8.4-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-blue.svg)

**ez-delivery** creates a release branch by cherry-picking commits from pull
requests that are linked to an issue carrying a specific label — for either
GitHub or GitLab.

## Table of contents

- [What is ez-delivery?](#what-is-ez-delivery)
- [Requirements](#requirements)
- [Getting started](#getting-started)
- [Configuring a project](#configuring-a-project)
- [Creating a delivery](#creating-a-delivery)
- [Upgrading a project config](#upgrading-a-project-config)
- [License](#license)

## What is ez-delivery?

Given a repository where pull requests are linked to an issue, and that
issue carries a "to deliver" label for a given environment, `ez-delivery`
assembles a release branch containing exactly the commits from those pull
requests. It walks you through selecting the environment, reviewing which
PRs match, creating the branch, resolving any cherry-pick conflicts, and
optionally pushing the branch and updating the source issues' labels once
delivered.

## Requirements

- [Docker](https://www.docker.com/), to run the published image.
- A GitHub personal access token or a GitLab token with API access to the
  repository you want to deliver from.

## Getting started

Pull the published image:

```bash
docker pull ghcr.io/baptistecontreras/ez-delivery:latest
```

The image expects a few things mounted in: your SSH key (for git operations
over SSH), your git identity, a persistent directory for `ez-delivery`'s own
project configs, and the target repository itself. The examples below
assume this alias:

```bash
alias ez-delivery='docker run --rm -it \
  -v ~/.ssh:/home/ez-delivery/.ssh \
  -v ~/.gitconfig:/home/ez-delivery/.gitconfig \
  -v ~/.ez-delivery:/home/ez-delivery/.ez \
  -v "$(pwd)":/app \
  -e USER=$(id -u) \
  ghcr.io/baptistecontreras/ez-delivery:latest \
  ez-delivery'
```

Add it to your shell profile (`~/.bashrc`, `~/.zshrc`, etc.), then run
`ez-delivery <command>` from inside the target repository's working
directory — every command below assumes this alias is in place.

## Configuring a project

Each project you deliver from needs a one-time configuration:

```bash
ez-delivery init-project-config
```

This asks, interactively:

- A **project name** — how you'll refer to this project in later commands.
- The project's **source path** and its **default base branch**.
- The **repo type** (GitHub or GitLab), then the credentials for it: owner
  (GitHub) or namespace (GitLab), repository name, and an API token.
- One or more **environments**, each with a name and its pair of labels:
  the "already delivered" label (what an issue gets swapped to once delivered) and
  the "to deliver" label (marks an issue as ready for this environment).
  You can add as many environments as you need.

The resulting config is stored under `~/.ez-delivery` (or wherever
`~/.ez-delivery` is mounted, per the alias above) and referenced by the
project name in every later command.

## Creating a delivery

```bash
ez-delivery package <project>
```

This:

1. Asks which **environment** to deliver, then fetches every pull request
   whose linked issue carries that environment's "to deliver" or "already
   delivered" label, and shows them in a table for review.
2. If **no matching pull requests** are found, offers to create an empty
   release branch anyway instead of just stopping — useful for cutting a
   release branch ahead of time even when there's nothing to deliver yet.
3. Once you confirm, asks for the **delivery branch name** (defaulting to
   `<environment>-<timestamp>`) and which **base branch** to branch from, then
   updates the base branch and creates the delivery branch from it.
4. **Cherry-picks each pull request's commits** onto the delivery branch.
   If a cherry-pick conflicts, the command pauses — resolve the conflict in
   your working directory and re-run the exact same `package <project>`
   command to resume from where it left off.
5. Once every PR is merged, optionally **pushes** the delivery branch and
   **updates labels** on the source issues (swapping "to deliver" for
   "already delivered"; currently GitLab-only) — both are separate yes/no prompts, so you can
   review the branch locally before either happens.

## Upgrading a project config

`ez-delivery`'s project config format is versioned. If a future release
changes that format, running any command against an outdated config will
tell you so and point you here:

```bash
ez-delivery migrate-config <project>
```

This backs up the existing config file, then upgrades it through every
intermediate version automatically. If the config is already current, it
does nothing and tells you so.

## License

MIT — see [LICENSE](LICENSE).