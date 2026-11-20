# Creative Studio temporary outage recovery

A user screenshot showed Service Unavailable in the embedded Creative Studio during a deployment. A subsequent authenticated live browser check loaded the same Studio successfully. The old polling routine deferred while the mouse was over the workspace, which could delay recovery from a transient error.

Creative data loading now retries network failures and HTTP 408/429/502/503/504 with a progressive 5-to-30-second delay. The retry reads current data only; it never resubmits a brief, approval, upload or other write. It defers while a dialog, unsaved capacity edits, active input, drag, mutation or hidden page would make refresh disruptive. Successful loading clears the temporary message. Last valid results remain visible during a background outage. Permission failures are not retried automatically. A visible Retry control remains available.

Production release verified HTTP 200 for both Creative source data and presentation/chart data, and the new recovery code in the published asset bundle. Zero active matching records was confirmed against the source after the user-authorized archive; no data was fabricated. An authenticated browser then verified both Showcase and the Overview chart loaded without the error banner. HR Supplies also showed all six retained vendor contacts. Existing archived source records remain retained.

Validation: all 71 JavaScript tests passed, including temporary outage recovery despite hover, last-results preservation, dialog deferral and nonretrying permission failures. Production rollback safeguards restored files when the initial verification script omitted required report query parameters; the corrected verification and release succeeded. No business data was modified by this recovery deployment.
