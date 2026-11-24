/*
 * Entrypoint of the admin area (templates/admin/base.html.twig), next to "app". Starts Stimulus, so visitors of the
 * public pages load no extra JavaScript (decision 2026-11-16, umsetzung-admin.md C4 question 2). Logged-in admins
 * also get it on public pages, for editing texts in place (templates/base.html.twig, docs/planung/cms.md K4).
 */
import './stimulus_bootstrap.js';
