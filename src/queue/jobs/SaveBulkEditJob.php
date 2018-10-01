<?php
/**
 * Bulk Edit plugin for Craft CMS 3.x
 *
 * Bulk edit entries
 *
 * @link      https://venveo.com
 * @copyright Copyright (c) 2018 Venveo
 */

namespace venveo\bulkedit\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use venveo\bulkedit\BulkEdit;
use venveo\bulkedit\records\EditContext;
use yii\base\Exception;

/**
 * @author    Venveo
 * @package   BulkEdit
 * @since     1.0.0
 */
class SaveBulkEditJob extends BaseJob
{
    // Public Properties
    // =========================================================================


    /** @var EditContext */
    public $context;

    // Public Methods
    // =========================================================================
    public function execute($queue = null)
    {
        $elementIds = BulkEdit::$plugin->bulkEdit->getPendingElementIdsFromContext($this->context);
        $totalSteps = count($elementIds);
        try {
            foreach ($elementIds as $key => $elementId) {
                $element = Craft::$app->getElements()->getElementById($elementId, null, $this->context->siteId);
                if (!$element) {
                    continue;
                }
                $history = BulkEdit::$plugin->bulkEdit->getPendingHistoryForElement($this->context, $element->id)->all();
                try {
                    Craft::info('Starting processing bulk edit job', __METHOD__);
                    BulkEdit::$plugin->bulkEdit->processHistoryItemsForElement($history, $element);
                } catch (\Exception $e) {
                    Craft::error('Could not save element in bulk edit job... '. $e->getMessage(), __METHOD__);
                    throw new Exception('Couldn’t save element ' . $element->id . ' (' . get_class($element) . ')');
                } catch (\Throwable $e) {
                    throw $e;
                }

                if (($key + 1) === $totalSteps) {
                    try {
                        $this->context->delete();
                    } catch (\Exception $e) {
                        throw new Exception('Couldn’t delete context: ' . $e->getMessage());
                    }
                }

                $this->setProgress($queue, ($key + 1) / $totalSteps);
            }
        } catch (\Exception $e) {
            Craft::error('Failed to save... '. $e->getMessage(), __METHOD__);
            throw $e;
        }
    }

    // Protected Methods
    // =========================================================================

    protected function defaultDescription(): string
    {
        return Craft::t('bulkedit', 'Bulk Edit in progress by {name}', ['name' => $this->context->owner->firstName]);
    }
}
