<?php

/**
 * JCOGS Image Pro - Pipeline Stage Interface
 * ==========================================
 * Phase 2: Native EE7 implementation pipeline architecture
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\Pipeline;

/**
 * Pipeline Stage Interface
 * 
 * Defines the contract that all pipeline stages must implement.
 * Each stage receives a Context object and performs its specific
 * processing task, updating the context as needed.
 * 
 * Stage Responsibilities:
 * - Perform specific processing task
 * - Update context with results
 * - Handle errors gracefully
 * - Set exit conditions when appropriate
 */
interface StageInterface 
{
    /**
     * Execute the stage
     * 
     * Performs the stage's specific processing task. Should update
     * the context with results and handle any errors that occur.
     * 
     * @param Context $context Processing context
     * @throws \Exception If critical error occurs
     */
    public function execute(Context $context): void;
    
    /**
     * Get stage name
     * 
     * @return string Stage identifier
     */
    public function get_name(): string;
    
    /**
     * Check if stage should be skipped
     * 
     * Allows stages to be conditionally executed based on context state.
     * 
     * @param Context $context Processing context
     * @return bool True if stage should be skipped
     */
    public function should_skip(Context $context): bool;
}
