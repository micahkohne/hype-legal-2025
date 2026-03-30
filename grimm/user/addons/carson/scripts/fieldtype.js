(function () {
    const carson = () => {
        /**
         * @param selector
         * @param context
         * @returns {*}
         */
        let getElement = function (selector, context = null) {
            if (!context) {
                context = document;
            }

            return context.querySelector(selector);
        }

        /**
         * @param selector
         * @param context
         * @returns {NodeListOf<*>}
         */
        let getElements = function (selector, context = null) {
            if (!context) {
                context = document;
            }

            return context.querySelectorAll(selector);
        }

        const serializeArray = (form) => {
            const formData = new FormData(form);
            const pairs = [];
            for (const [name, value] of formData) {
                pairs.push({ name, value });
            }
            return pairs;
        }

        let $button
        let $contextToggle
        let $field
        let forceContext = ''
        let $publishForm = getElement('[data-publish] > form')
        let actionUrl = ''
        let previewUrl = getElement('.live-preview__preview iframe')?.dataset.url
        let prompt = ''
        let target = ''
        let type = ''
        let requestType = 'text'
        let history = {}

        getElements('a.carson').forEach((button) => {
            button.addEventListener('click', function (event) {
                $button = button
                initField(button, event);
            })
        });

        const findClosestParent = (element) => {
            // Order matters, leave normal fields for last
            const closestParentSelectors = [
                '.blocksft-atomcontainer', // Bloqs
                'td[data-column-id]', // Grid & Simple Grid
                '.fluid__item-field', // Fluid
                '.field-control', // Normal field
            ]

            for (const selector of closestParentSelectors) {
                const parent = element.parentNode.closest(selector)

                if (parent) {
                    return parent
                }
            }

            return null
        }

        if (getElement('#carson_omni')) {
            const menuTemplateElement = getElement('#carsonOmniMenuTemplate');
            const menuTemplateContent = menuTemplateElement.innerHTML.toString()

            const addOmniButtons = (context) => {
                const fieldTypes = EE?.carson?.fieldTypes || [];
                const fieldTypeSelectors = [
                    'input[name="title"]',
                    // 'input[name^="field_id"][type="text"]',
                    // 'textarea[name^="field_id"]',
                ];

                for (type in fieldTypes) {
                    const fieldNames = JSON.parse(fieldTypes[type])
                    fieldNames.forEach((fieldName) => {
                        fieldTypeSelectors.push(type + '[name*="' + fieldName + '"]')
                    })
                }

                const fields = context ?
                    getElements(fieldTypeSelectors.join(','), context) :
                    getElements(fieldTypeSelectors.join(','))
                ;

                if (context) {
                    // On initial page load buttons may be added to Grid, Fluid, or Bloqs fields, but when new rows are
                    // added and the mutation observer is triggered, we'll need to add new ones to the DOM that have
                    // events bound to them, so delete the old ones in the DOM that don't have the events.
                    getElements('.carson-omni-container', context).forEach((element) => {
                        element.remove()
                    })
                }

                for (const field of fields) {
                    const target = field.getAttribute('name')
                    const isHidden = field.getAttribute('type') === 'hidden'

                    if (!isHidden) {
                        createOmniButton(field, target)
                    }
                }

                // Listen to all ajax requests being made. This is a little excessive
                // but the only way to target the image slide out panel.
                $(document).ajaxSuccess((event, xhr, settings) => {
                    // It's an image modal view within the CP
                    const urlMatch = settings.url.match(/cp\/files\/file\/view\/(\d+)&modal_form=y/)
                    if (urlMatch) {
                        const target = '.file-preview-modal__form textarea[name=\'description\']'
                        const descField = getElement(target)

                        // I wish the meta data was attached to data attributes as well, this is kind of jank.
                        const fileType = getElement('.f_meta-info-file_type')

                        if (descField && fileType?.innerText === 'Image') {
                            createImageOmniButton(descField, target, urlMatch[1])
                        }
                    }
                });
            }

            const createOmniButton = (field, target) => {
                const fieldParent = findClosestParent(field)
                // console.log(field, fieldParent);

                if (!fieldParent) {
                    console.log('Could not find parent for ' + field)
                    return
                }

                const menu = document.createElement('div');
                menu.innerHTML = menuTemplateContent.replaceAll('{{target}}', target)

                const promptsVisible = getElements('a.carson-prompt:not(.hidden)', menu.firstChild);

                // Don't add the whole menu if there are no prompts
                if (promptsVisible.length === 0) {
                    return;
                }

                fieldParent.setAttribute('style', 'position: relative;')
                fieldParent.appendChild(menu.firstChild)

                getElements('a.carson-prompt', fieldParent).forEach((link) => {
                    link.addEventListener('click', (event) => {
                        $button = link
                        initOmni(link, field.value, event)
                    })
                });
            }

            const createImageOmniButton = (field, target, fileId) => {
                const menu = document.createElement('div');
                menu.innerHTML = menuTemplateContent.replaceAll('{{target}}', target)

                const fieldParent = findClosestParent(field)

                if (!fieldParent) {
                    console.log('Could not find parent for ' + field)
                    return
                }

                const imagePrompt = getElement('.carson-prompt-image', menu)
                imagePrompt.classList.remove('hidden')

                fieldParent.setAttribute('style', 'position: relative;')
                fieldParent.appendChild(menu.firstChild)

                getElements('a.carson-prompt', fieldParent).forEach((link) => {
                    link.addEventListener('click', (event) => {
                        $button = link
                        if (link.dataset.requestType === 'image') {
                            initOmni(link, fileId, event)
                        } else {
                            initOmni(link, field.value, event)
                        }
                    })
                });
            }

            addOmniButtons();

            const showUndoButton = () => {
                const menu = getElement('.carson-omni-container[data-target="' + target + '"]')
                if (menu) {
                    getElement('.undo-divider', menu).classList.remove('hidden')
                    getElement('.undo', menu).classList.remove('hidden')
                }
            }

            const hideUndoButton = () => {
                const menu = getElement('.carson-omni-container[data-target="' + target + '"]')
                if (menu) {
                    getElement('.undo-divider', menu).classList.add('hidden')
                    getElement('.undo', menu).classList.add('hidden')
                }
            }

            const addToHistory = (fieldName, content) => {
                if (!history.hasOwnProperty(fieldName)) {
                    history[fieldName] = []
                }

                history[fieldName].push(content)
            }

            const getFromHistory = (fieldName) => {
                if (!history.hasOwnProperty(fieldName)) {
                    return
                }

                return history[fieldName].pop()
            }

            const hasHistory = (fieldName) => {
                if (!history.hasOwnProperty(fieldName)) {
                    return false
                }

                return history[fieldName].length > 0
            }

            const initOmni = ($link, content, event) => {
                event.preventDefault()

                actionUrl = EE.carson.fetchDataUrl || ''
                target = $link.dataset.target ? [$link.dataset.target] : []
                type = $link.dataset.type
                prompt = $link.dataset.prompt
                requestType = $link.dataset.requestType;

                if ($link.classList.contains('undo')) {
                    const historyContent = getFromHistory(target)
                    const response = {}
                    response[target] = historyContent

                    if (historyContent) {
                        buttonWorking()
                        handleResponse({
                            content: response
                        })
                    }
                } else {
                    buttonWorking()
                    showUndoButton()
                    addToHistory(target, content)
                    makeRequest(content)
                }

                if (!hasHistory(target)) {
                    hideUndoButton()
                }
            };

            const createMutationObservers = (complexFieldSelectors) => {
                var observer = new MutationObserver((mutations, observer) => {
                    mutations.forEach((mutation) => {
                        mutation.addedNodes.forEach((node) => {
                            addOmniButtons(node)

                            // If a Grid inside of Fluid
                            if (node.dataset.fieldType === 'grid') {
                                createMutationObservers([
                                    '.fluid > .js-sorting-container .grid-field__table tbody',
                                ].join(','));
                            }
                        })
                    })
                });

                getElements(complexFieldSelectors).forEach((element) => {
                    observer.observe(element, {
                        attributes: false,
                        childList: true,
                        characterData: false
                    });
                });
            }

            createMutationObservers([
                '.blocksft-blocks',
                '.grid-field__table tbody',
                '.fluid > .js-sorting-container',
            ].join(','));
        }

        // This is our basic implementation of binding to the Assistant or SEO fields if added to channel
        const initField = ($button, event) => {
            event.preventDefault();

            $field = $button.parentNode.closest('.carson-container');
            $contextToggle = getElement('[name="carson_context"]', $field);
            actionUrl = $button.dataset.actionUrl;
            let data = serializeArray($publishForm);
            let $promptField = getElement('[name="carson_prompt"]', $field);
            prompt = $promptField.value;
            target = $button.dataset.target ? JSON.parse($button.dataset.target) : [];
            type = $button.dataset.type;
            forceContext = $button.dataset.forceContext;

            buttonWorking();

            // We're making a general, contextless request to OpenAI. Do whatever is asked in the prompt.
            if (forceContext === 'n' || ($contextToggle && $contextToggle.value === 'n')) {
                makeRequest();
                return;
            }

            // Attempt to use Live Preview first, it will be the most accurate because
            // it'll be sending full contextual HTML with heading tags etc.
            if (previewUrl) {
                fetchPreview();
                return;
            }

            let values = [];

            data.forEach(function (field) {
                if (isValidField(field.name) && isString(field.value)) {
                    values.push(field.value);
                }
            });

            // If Live Preview is not enabled, try to use the raw form data.
            makeRequest(values.join(' '));
        };

        let isValidField = function (fieldName) {
            return fieldName === 'title' || fieldName.match(/^field_id_/);
        };

        let isString = function (value) {
            if (value === '' || !isNaN(value)) {
                return false;
            }

            if (value === 'yes' || value === 'no' || value === 'true' || value === 'false') {
                return false;
            }

            return (typeof value === 'string' || value instanceof String);
        };

        let buttonReset = function () {
            if (!$button) {
                return;
            }

            let $buttonLabel = getElement('span', $button);
            let $buttonIcon = getElement('.fas', $button);

            $button.classList.remove('work');
            $button.removeAttribute('disabled');
            $buttonIcon.classList.remove('fa-fade');
            $buttonLabel.textContent = $button.dataset.text;
        };

        let buttonWorking = function () {
            if (!$button) {
                return;
            }

            let $buttonLabel = getElement('span', $button);
            let $buttonIcon = getElement('.fas', $button);
            let buttonText = $buttonLabel.textContent;

            $button.classList.add('work');
            $button.dataset.text = buttonText;
            $button.setAttribute('disabled', 'disabled');
            $buttonIcon.classList.add('fa-fade');
            $buttonLabel.textContent = $button.dataset.workText;
        }

        // let fetchPreview = function () {
        //     fetch(previewUrl, {
        //         headers: {
        //             'Access-Control-Allow-Origin': window.location.origin,
        //             'Content-Type': 'application/json;'
        //         },
        //         method: 'POST',
        //         mode: 'no-cors',
        //         data: serializeArray($publishForm)
        //     })
        //         .then(response => response.text())
        //         .then(async response => makeRequest(response))
        //         .catch(error => handleError(error))
        //     ;
        // };

        // let makeRequest = async function (content) {
        //     const data = new FormData();
        //     data.append('content', content);
        //     data.append('prompt', prompt);
        //     data.append('target', target);
        //
        //     fetch(actionUrl, {
        //         headers: {
        //             'Access-Control-Allow-Origin': window.location.origin,
        //             'Content-Type': 'multipart/form-data'
        //         },
        //         method: 'POST',
        //         body: data,
        //     })
        //         .then(response => response.json())
        //         .then(response => handleResponse(response))
        //         .catch(error => handleError(error))
        //     ;
        // };

        let fetchPreview = function () {
            $.ajax({
                type: 'POST',
                dataType: 'html',
                url: previewUrl,
                crossDomain: true,
                beforeSend: function (request) {
                    request.setRequestHeader("Access-Control-Allow-Origin", window.location.origin);
                },
                data: serializeArray($publishForm),
                complete: function (xhr) {
                    if (xhr.responseText !== undefined) {
                        makeRequest(xhr.responseText);
                    }
                },
                error: function (xhr) {
                    handleError(xhr);
                }
            });

        };

        let makeRequest = function (content) {
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: actionUrl,
                data: {
                    content: content,
                    prompt: prompt,
                    target: target,
                    requestType: requestType || 'text'
                },
                complete: function (xhr) {
                    if (xhr.responseJSON !== undefined) {
                        buttonReset();
                        handleResponse(xhr.responseJSON);
                    }
                },
                error: function (xhr) {
                    handleError(xhr);
                }
            });
        };

        let handleResponse = function (response) {
            buttonReset();

            // @todo Remove this eventually when Carson has matured, but for now leave it in for support and debugging.
            console.log(response);

            if ('errorMessage' in response) {
                handleError(response);
                return;
            }

            if (!'content' in response) {
                handleError({
                    'errorMessage': 'No content found in response.',
                });
            }

            if (type === 'assistant' && typeof response === 'object') {
                target.forEach(function (fieldName) {
                    let $field = getElement('[name="' + fieldName + '"]')

                    // Do we have a more complicated target, e.g. an Image edit modal?
                    if (Array.from(fieldName)[0] === '.' || Array.from(fieldName)[0] === '#') {
                        $field = getElement(fieldName)
                    }

                    let $container = findClosestParent($field);

                    let value =
                        response?.content?.[fieldName] ??
                        response?.[fieldName] ??
                        '';

                    // Or conditions are because apparently RTE fields inside of Grid don't get the same rendering
                    // output treatment as standalone fields. Why?
                    if ($field.classList.contains('rte-textarea') || $container.classList.contains('grid-rte')) {
                        if ($field.classList.contains('redactor-box') || $field.dataset?.config.includes('Redactor')) {
                            if ($field.classList.contains('redactor-source')) {
                                // Old Redactor(?)
                                $R($field, 'source.setCode', value)
                            } else {
                                // Redactor X
                                let instance = RedactorX('[name="' + fieldName + '"]')
                                instance.editor.setContent({html: value});
                            }
                        } else {
                            let instance = getElement('.ck-editor__editable', $container)

                            if (instance) {
                                instance.ckeditorInstance.setData(value)
                            } else {
                                // It's a CKEditor field with a deferred initialization.
                                // Set the value. Need to figure out how to initialize it.
                                $field.value = value
                            }
                        }
                    } else {
                        $field.value = value
                    }
                })

                return
            }

            if (type === 'seo' && typeof response === 'object' && response.hasOwnProperty('content')) {
                setFieldValue('[name="seo_lite__seo_lite_title"]', response.content.title);
                setFieldValue('[name="seo_lite__seo_lite_og_title"]', response.content.title);
                setFieldValue('[name="seo_lite__seo_lite_twitter_title"]', response.content.title);
                setFieldValue('[name="seo_lite__seo_lite_description"]', response.content.description);
                setFieldValue('[name="seo_lite__seo_lite_og_description"]', response.content.description);
                setFieldValue('[name="seo_lite__seo_lite_twitter_description"]', response.content.description);

                // Removed in later versions due to not being as relevant by Google.
                // https://github.com/0to9Digital/SEO-Lite-Pro/issues/17
                setFieldValue('[name="seo_lite__seo_lite_keywords"]', response.content.keywords.join(','));
                setFieldValue('[name="seo_lite__seo_lite_og_keywords"]', response.content.keywords.join(','));

                setFieldValue('[name="seeo__title"]', response.content.title);
                setFieldValue('[name="seeo__description"]', response.content.description);
                setFieldValue('[name="seeo__keywords"]', response.content.keywords);
                setFieldValue('[name="seeo__og_title"]', response.content.title);
                setFieldValue('[name="seeo__og_description"]', response.content.description);
                setFieldValue('[name="seeo__og_keywords"]', response.content.keywords);
                setFieldValue('[name="seeo__twitter_title"]', response.content.title);
                setFieldValue('[name="seeo__twitter_description"]', response.content.description);
                setFieldValue('[name="seeo__twitter_keywords"]', response.content.keywords);

                // Now handle custom target fields
                let seoFields = EE.carson.seoFields || {};

                for (let fieldName in seoFields) {
                    let fieldIds = seoFields[fieldName];
                    fieldIds.forEach(function (fieldId) {
                        setFieldValue('[name="field_id_' + fieldId + '"]', response.content[fieldName]);
                    });
                }
            }
        };

        let handleError = function (response) {
            buttonReset();

            if (response.errorMessage) {
                alert(response.errorMessage);
            }

            if (response.statusText === 'parseerror') {
                console.log('Invalid JSON response: ' + response.responseText);
            }
        }

        let setFieldValue = function (selector, value) {
            let field = getElement(selector);

            if (field) {
                field.value = value;
            }
        }
    };

    document.addEventListener('DOMContentLoaded', carson, false);

})();
