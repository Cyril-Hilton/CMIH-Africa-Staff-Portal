# Brands brief success rate

The activation summary previously divided closed/won briefs by closed briefs with a won/lost outcome. Pending briefs were excluded, so three wins and one pending brief showed 100%.

The Success Rate card now divides closed briefs with a recorded won outcome by all briefs received within the selected owner, search and reporting period. Three wins out of four received briefs therefore show 75%. Pending, lost and closed-but-unconfirmed outcomes remain in the denominator. No received briefs remains unmeasured; pending-only briefs show 0%. The explanatory note uses the same definition. Other department conversion measures and performance scoring are unchanged.

Regression verification: Brands workspace suite passed **22 tests / 243 assertions**, including the reported four-brief case, pending-only and won-only search filters, owner isolation, team aggregation, an empty period, and closed records without a won outcome. Tests submit through the real reporting endpoint into the test database; no production entries are created.

Production deployed and verified through the Brands data controller. Weekly, monthly and yearly selections each returned **4 received, 1 pending, 3 closed, 3 confirmed wins, 75% success**. Protected business data was unchanged. One runtime file was installed with hash checks and rollback backup `cmih-brands-success-rate-20261124-20261124-092302`; the temporary deployment schedule was removed.
