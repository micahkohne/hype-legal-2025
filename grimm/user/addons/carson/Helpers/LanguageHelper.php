<?php

namespace BoldMinded\Carson\Helpers;

use BoldMinded\Carson\Dependency\Litzinger\Basee\App;
use BoldMinded\Publisher\Service\Request;

class LanguageHelper
{
    private Request $request;

    private bool|null $isPublisherInstalled = null;
    private bool|null $isTranscribeInstalled = null;

    public function isPublisherInstalled(): bool
    {
        if ($this->isPublisherInstalled === null) {
            $this->isPublisherInstalled = App::isAddonInstalled('publisher');
        }

        if ($this->isPublisherInstalled) {
            /** @var Request $request */
            $this->request = ee('publisher:Request');
        }

        return $this->isPublisherInstalled;
    }

    public function isTranscribeInstalled(): bool
    {
        if ($this->isTranscribeInstalled === null) {
            $this->isTranscribeInstalled = App::isAddonInstalled('transcribe');
        }

        return $this->isTranscribeInstalled;
    }

    public function getCurrentLanguageName(): string
    {
        if ($this->isPublisherInstalled()) {
            return $this->request->getCurrentLanguage()->getLongName() ?? '';
        }

        if ($this->isTranscribeInstalled()) {
            $lang = $this->getCurrentTranscribeLanguage();
            return $lang['name'] ?? '';
        }

        return '';
    }

    public function isDefaultLanguage(): bool
    {
        if ($this->isPublisherInstalled()) {
            return $this->request->isDefaultLanguage();
        }

        if ($this->isTranscribeInstalled()) {
            $lang = $this->getCurrentTranscribeLanguage();

            try {
                $settings = ee()->db
                    ->where('site_id', ee()->config->item('site_id'))
                    ->get('transcribe_settings')
                    ->result_array()[0];

                return $settings['language_id'] === $lang['id'];
            } catch (\Exception $exception) {
                return false;
            }
        }

        return true;
    }

    /**
     * Jump through some hoops b/c there is no global context when inside the CP
     * of what the current language is when editing an entry.
     *
     * @return array
     */
    private function getCurrentTranscribeLanguage(): array
    {
        try {
            $entryId = App::getEntryIdFromRequest();
            $parentId = ee()->input->get('parent_id');

            if ($entryId) {
                ee()->db->where('entry_id', $entryId);
                $entryLanguage = ee()->db->get('transcribe_entries_languages', 1);
                $entryLanguage = $entryLanguage->row();
            } elseif ($parentId) {
                ee()->db->where('entry_id', $parentId);
                $entryLanguage = ee()->db->get('transcribe_entries_languages', 1);
                $entryLanguage = $entryLanguage->row();
            }

            if ($entryLanguage->language_id) {
                return ee()->db
                    ->where('id', $entryLanguage->language_id)
                    ->get('transcribe_languages')
                    ->result_array()[0];
            }
        } catch (\Exception $exception) {
            return [];
        }

        return [];
    }
}
