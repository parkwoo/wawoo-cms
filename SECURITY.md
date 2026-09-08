# Security Policy

wawoo-cms takes security seriously. This document explains how to report a
vulnerability and what to expect.

## Supported versions

Only the latest tagged release of the core (`wawoo-cms`) receives security
fixes. Optional plugins and themes are maintained in their own repositories;
each follows the same rule — the latest tagged release is the supported one.

If you run an older tag, upgrade to the latest release before reporting.

## Reporting a vulnerability

Please **do not open a public issue** for security problems. Instead, send a
private report:

- GitHub private vulnerability reporting: use the "Report a vulnerability"
  button on the repository's Security tab.
- Email: hello@cms.wawoo.jp

Include as much of the following as possible:

1. Which repository and which tag/commit is affected.
2. A description of the issue and the impact you observed.
3. Steps to reproduce (a minimal wawoo-cms install with only the affected
   plugin enabled is ideal).
4. Whether the issue affects the core, a shipped plugin, or an optional
   plugin/theme.

## What happens next

- You will get an acknowledgement within 3 business days.
- We will confirm the issue, determine affected versions, and prepare a fix.
- A fix is released as a new tag in the affected repository.
- The advisory is published after the fix is out; we will credit you if you
  want to be credited.

## Scope

In scope:

- The core (`includes/`, `bin/`, shipped content handlers).
- Plugins and themes in the `wawoo-plugin-*` / `wawoo-theme-*` repositories.

Out of scope:

- Third-party CDN assets referenced by plugins (report those to their owners).
- Local development setups where you intentionally expose `config.local.php`
  or the admin console to untrusted networks.
