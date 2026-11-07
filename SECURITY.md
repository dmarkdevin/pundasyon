# Security policy

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: on the repository, go to **Security** → **Report a vulnerability**. Include:

- what the problem is and what an attacker could do with it
- steps to reproduce, or a proof of concept
- the version or commit you tested

You'll get an answer within 7 days. Once a fix is ready, it's released and credited to you unless you'd rather stay anonymous.

## Supported versions

Security fixes go into the latest release. Keep your copy up to date as described in [upgrading](docs/upgrading.md).

## Scope

In scope: the code in this repository. Out of scope: problems that need an already compromised server or `.env` file, missing hardening that's documented as your responsibility in the [deployment checklist](docs/deployment.md), and vulnerabilities in dependencies that are already fixed upstream (please report those to the dependency).

What Pundasyon does to protect accounts and data is described in [docs/security.md](docs/security.md).
