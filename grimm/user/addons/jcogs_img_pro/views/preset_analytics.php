<?php
/**
 * JCOGS Image Pro - Preset Analytics View
 * ========================================
 * ExpressionEngine 7 Add-on View Template for Preset Usage Analytics
 * 
 * This view provides comprehensive analytics and insights for individual presets,
 * including usage frequency, performance metrics, error tracking, comparative analysis,
 * and actionable insights. Features interactive statistics management capabilities.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Preset Analytics Interface Implementation
 * 
 * Template Variables:
 * @var array   $preset_analytics  Complete analytics data for the specific preset
 * @var array   $usage_summary     Site-wide usage statistics and comparison data
 * @var string  $back_url          URL to return to the preset management interface
 * @var string  $base_url          Base URL for asset loading and navigation
 * @var string  $csrf_token        CSRF protection token for form submissions
 */

// Extract analytics data for easier access
$analytics = $preset_analytics;
$summary = $usage_summary;

// Helper function for time ago display
if (!function_exists('time_ago_display')) {
    function time_ago_display($timestamp) {
        $diff = time() - $timestamp;
        if ($diff < 3600) {
            return floor($diff / 60) . ' mins ago';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . ' hours ago';
        } else {
            return floor($diff / 86400) . ' days ago';
        }
    }
}
?>

<div class="preset-analytics-container">
    
    <!-- Header with Preset Info -->
    <div class="panel preset-info-panel">
        <div class="panel-heading">
            <h3>📊 Analytics: <?= htmlspecialchars($analytics['preset_name']) ?></h3>
            <small class="text-muted">
                Created <?= date('M j, Y', $analytics['created_date']) ?> 
                (<?= $analytics['days_since_creation'] ?> days ago)
            </small>
        </div>
        <div class="panel-body">
            <div class="analytics-summary-grid">
                <div class="metric-card usage-metric">
                    <div class="metric-value"><?= number_format($analytics['usage_count']) ?></div>
                    <div class="metric-label">Total Uses</div>
                    <div class="metric-detail">
                        <?= $analytics['avg_daily_usage'] ?> per day avg
                    </div>
                </div>
                
                <div class="metric-card error-metric">
                    <div class="metric-value"><?= number_format($analytics['error_count']) ?></div>
                    <div class="metric-label">Errors</div>
                    <div class="metric-detail">
                        <?php if ($analytics['usage_count'] > 0): ?>
                            <?= round(($analytics['error_count'] / $analytics['usage_count']) * 100, 1) ?>% error rate
                        <?php else: ?>
                            No usage data
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="metric-card performance-metric">
                    <div class="metric-value">
                        <?php if (!empty($analytics['performance']['avg_execution_time'])): ?>
                            <?= round($analytics['performance']['avg_execution_time'] * 1000, 1) ?>ms
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </div>
                    <div class="metric-label">Avg Speed</div>
                    <div class="metric-detail">Resolution time</div>
                </div>
                
                <div class="metric-card activity-metric">
                    <div class="metric-value">
                        <?php if ($analytics['last_used_date']): ?>
                            <?= time_ago_display($analytics['last_used_date']) ?>
                        <?php else: ?>
                            Never
                        <?php endif; ?>
                    </div>
                    <div class="metric-label">Last Used</div>
                    <div class="metric-detail">Most recent usage</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Usage Analysis -->
    <?php if ($analytics['usage_count'] > 0): ?>
    <div class="panel usage-analysis-panel">
        <div class="panel-heading">
            <h4>📈 Usage Analysis</h4>
        </div>
        <div class="panel-body">
            <div class="analysis-content">
                <div class="usage-insights">
                    <h5>Insights</h5>
                    <ul class="insight-list">
                        <?php if ($analytics['avg_daily_usage'] > 1): ?>
                            <li class="insight-positive">
                                <i class="fas fa-trending-up"></i>
                                High usage preset (<?= $analytics['avg_daily_usage'] ?> uses/day)
                            </li>
                        <?php elseif ($analytics['avg_daily_usage'] > 0.1): ?>
                            <li class="insight-neutral">
                                <i class="fas fa-chart-line"></i>
                                Moderate usage preset (<?= $analytics['avg_daily_usage'] ?> uses/day)
                            </li>
                        <?php else: ?>
                            <li class="insight-warning">
                                <i class="fas fa-trending-down"></i>
                                Low usage preset - consider review
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($analytics['error_count'] > 0): ?>
                            <?php $error_rate = ($analytics['error_count'] / $analytics['usage_count']) * 100; ?>
                            <?php if ($error_rate > 10): ?>
                                <li class="insight-error">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    High error rate (<?= round($error_rate, 1) ?>%) - needs attention
                                </li>
                            <?php elseif ($error_rate > 5): ?>
                                <li class="insight-warning">
                                    <i class="fas fa-exclamation-circle"></i>
                                    Moderate error rate (<?= round($error_rate, 1) ?>%) - monitor closely
                                </li>
                            <?php else: ?>
                                <li class="insight-positive">
                                    <i class="fas fa-check-circle"></i>
                                    Low error rate (<?= round($error_rate, 1) ?>%) - performing well
                                </li>
                            <?php endif; ?>
                        <?php else: ?>
                            <li class="insight-positive">
                                <i class="fas fa-check-circle"></i>
                                No errors recorded - excellent reliability
                            </li>
                        <?php endif; ?>
                        
                        <?php if (!empty($analytics['performance']['avg_execution_time'])): ?>
                            <?php $avg_ms = $analytics['performance']['avg_execution_time'] * 1000; ?>
                            <?php if ($avg_ms < 50): ?>
                                <li class="insight-positive">
                                    <i class="fas fa-bolt"></i>
                                    Fast performance (<?= round($avg_ms, 1) ?>ms avg)
                                </li>
                            <?php elseif ($avg_ms < 200): ?>
                                <li class="insight-neutral">
                                    <i class="fas fa-clock"></i>
                                    Good performance (<?= round($avg_ms, 1) ?>ms avg)
                                </li>
                            <?php else: ?>
                                <li class="insight-warning">
                                    <i class="fas fa-hourglass-half"></i>
                                    Slow performance (<?= round($avg_ms, 1) ?>ms avg) - optimize parameters
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Comparison Context -->
    <?php if (!empty($summary['most_popular'])): ?>
    <div class="panel comparison-panel">
        <div class="panel-heading">
            <h4>📊 How This Preset Compares</h4>
        </div>
        <div class="panel-body">
            <div class="comparison-content">
                <h5>Most Popular Presets on Your Site</h5>
                <div class="popular-presets-list">
                    <?php foreach ($summary['most_popular'] as $index => $popular): ?>
                        <div class="popular-preset-item <?= $popular['id'] == $analytics['preset_id'] ? 'current-preset' : '' ?>">
                            <span class="rank">#<?= $index + 1 ?></span>
                            <span class="preset-name">
                                <?= htmlspecialchars($popular['name']) ?>
                                <?php if ($popular['id'] == $analytics['preset_id']): ?>
                                    <span class="badge current-badge">This Preset</span>
                                <?php endif; ?>
                            </span>
                            <span class="usage-count"><?= number_format($popular['usage_count']) ?> uses</span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="site-totals">
                    <small class="text-muted">
                        Site totals: <?= number_format($summary['total_usage']) ?> preset uses across <?= $summary['total_presets'] ?> presets
                    </small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Actions -->
    <div class="panel actions-panel">
        <div class="panel-body">
            <div class="analytics-actions">
                <a href="<?= $back_url ?>" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Preset
                </a>
                
                <!-- Reset Statistics Button -->
                <button type="button" class="btn btn-warning" id="reset-statistics-btn" 
                        data-preset-id="<?= $analytics['preset_id'] ?>" 
                        data-preset-name="<?= htmlspecialchars($analytics['preset_name']) ?>">
                    <i class="fas fa-refresh"></i> Reset Statistics
                </button>
                
                <!-- Future: Export Analytics Data -->
                <button type="button" class="btn btn-default" disabled>
                    <i class="fas fa-download"></i> Export Data
                    <small>(Coming Soon)</small>
                </button>
            </div>
        </div>
    </div>
</div>
