# Upgrade PHP 8.4 to the release that fixes PHP2Shell (CVE-2026-109618, CVE-2026-109619).
# Remove this once DDEV's own web image ships PHP 8.4.27 or newer.
RUN apt-get update && apt-get install -y --only-upgrade 'php8.4*' && rm -rf /var/lib/apt/lists/*
# Stop the build if the upgrade did not reach a fixed version.
RUN php -r 'exit(version_compare(PHP_VERSION, "8.4.27", "<") ? 1 : 0);'
