<?php
declare(strict_types=1);

// Load authentication and shared functions.
require_once __DIR__ . '/../../app/auth/guard.php';

// Load the M4 match model.
require_once __DIR__ . '/../../app/models/itemMatch.php';

// Only admins can access this page.
require_role(ROLE_ADMIN);

// Get the data for the three sections.
$suggestions = item_match_suggestions();   // new suggestions found by the system
$savedPending = item_match_all(false);      // saved pairs waiting for confirmation
$confirmed = item_match_all(true);          // pairs the admin already confirmed

// Set the page title and navigation.
$page_title = 'Matches';
$active_nav = 'matches';

// Load the shared layout.
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/navbar.php';
?>

<main id="main" class="container page">

    <div class="page-header">
        <div>
            <p class="eyebrow">Administration</p>
            <h1>Matches</h1>
            <p class="muted">
                <?= e((string) (count($suggestions) + count($savedPending))) ?> possible matches to review,
                <?= e((string) count($confirmed)) ?> confirmed.
            </p>
        </div>
    </div>

    <!-- Section 1: suggestions found by the system -->
    <h2>Suggested matches</h2>
    <p class="muted">
        The system compares approved Lost and Found reports. A higher score means they are more alike.
    </p>

    <?php if ($suggestions === []): ?>

        <section class="card empty-state">
            <h2>No suggestions right now</h2>
            <p>There are no new Lost and Found reports that look alike.</p>
        </section>

    <?php else: ?>

        <div class="table-wrap card">
            <table>
                <thead>
                    <tr>
                        <th>Lost item</th>
                        <th>Found item</th>
                        <th>Score</th>
                        <th>Why</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($suggestions as $pair): ?>
                    <tr>
                        <td>
                            <strong><?= e($pair['lost']['item_name']) ?></strong><br>
                            <span class="muted">
                                <?= e($pair['lost']['category_name']) ?> &middot;
                                <?= e($pair['lost']['location']) ?> &middot;
                                <?= e((string) $pair['lost']['event_date']) ?><br>
                                Reported by <?= e($pair['lost']['reporter_name']) ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= e($pair['found']['item_name']) ?></strong><br>
                            <span class="muted">
                                <?= e($pair['found']['category_name']) ?> &middot;
                                <?= e($pair['found']['location']) ?> &middot;
                                <?= e((string) $pair['found']['event_date']) ?><br>
                                Reported by <?= e($pair['found']['reporter_name']) ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= (int) $pair['score'] ?>%</strong><br>
                            <span class="chip <?= $pair['label'] === 'Strong' ? 'chip-approved' : 'chip-pending' ?>">
                                <?= e($pair['label']) ?>
                            </span>
                        </td>

                        <td class="muted">
                            <?= e(implode(' ', $pair['reasons'])) ?>
                        </td>

                        <td>
                            <form method="POST" action="<?= e(handler_url('match_confirm_handler.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="lost_item_id" value="<?= (int) $pair['lost']['item_id'] ?>">
                                <input type="hidden" name="found_item_id" value="<?= (int) $pair['found']['item_id'] ?>">

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-small"
                                    data-confirm="Confirm this match?"
                                >
                                    Confirm match
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <!-- Section 2: saved pairs that are not confirmed yet -->
    <?php if ($savedPending !== []): ?>

        <h2 style="margin-top: 32px;">Saved, waiting for confirmation</h2>

        <div class="table-wrap card">
            <table>
                <thead>
                    <tr>
                        <th>Lost item</th>
                        <th>Found item</th>
                        <th>Saved on</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($savedPending as $match): ?>
                    <tr>
                        <td>
                            <strong><?= e($match['lost_item_name']) ?></strong><br>
                            <span class="muted">Reported by <?= e($match['lost_reporter_name']) ?></span>
                        </td>

                        <td>
                            <strong><?= e($match['found_item_name']) ?></strong><br>
                            <span class="muted">Reported by <?= e($match['found_reporter_name']) ?></span>
                        </td>

                        <td><?= e((string) $match['created_at']) ?></td>

                        <td>
                            <form method="POST" action="<?= e(handler_url('match_confirm_handler.php')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="lost_item_id" value="<?= (int) $match['lost_item_id'] ?>">
                                <input type="hidden" name="found_item_id" value="<?= (int) $match['found_item_id'] ?>">

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-small"
                                    data-confirm="Confirm this match?"
                                >
                                    Confirm match
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php endif; ?>

    <!-- Section 3: confirmed matches -->
    <h2 style="margin-top: 32px;">Confirmed matches</h2>

    <?php if ($confirmed === []): ?>

        <section class="card empty-state">
            <h2>No confirmed matches yet</h2>
            <p>Matches you confirm will be listed here.</p>
        </section>

    <?php else: ?>

        <div class="table-wrap card">
            <table>
                <thead>
                    <tr>
                        <th>Lost item</th>
                        <th>Found item</th>
                        <th>Confirmed by</th>
                        <th>Saved on</th>
                    </tr>
                </thead>
                <tbody>

                <?php foreach ($confirmed as $match): ?>
                    <tr>
                        <td>
                            <strong><?= e($match['lost_item_name']) ?></strong><br>
                            <span class="muted">Reported by <?= e($match['lost_reporter_name']) ?></span>
                        </td>

                        <td>
                            <strong><?= e($match['found_item_name']) ?></strong><br>
                            <span class="muted">Reported by <?= e($match['found_reporter_name']) ?></span>
                        </td>

                        <td><?= e((string) ($match['confirmed_by_name'] ?? '')) ?></td>

                        <td><?= e((string) $match['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>

                </tbody>
            </table>
        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>