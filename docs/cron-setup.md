# Cron Jobs

## Nonce cleanup

Remove expired Web3 auth nonces older than 24 hours. Add to crontab:

    0 */6 * * * /usr/bin/php /path/to/cloudmarkplaats/bin/cleanup-nonces.php >> /var/log/cloudmarkplaats-cron.log 2>&1

Runs every 6 hours.
