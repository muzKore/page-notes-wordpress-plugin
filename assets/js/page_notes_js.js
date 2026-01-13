/**
 * Page Notes JavaScript - Vanilla JS Version
 * This handles all the interactive functionality of the plugin
 * No jQuery needed! Pure JavaScript that's easier to understand.
 */

(function() {
    'use strict';
    
    // ====================================================================
    // MAIN OBJECT: This is our app's "brain" that holds all functionality
    // ====================================================================
    const PageNotes = {

        // These are like variables that belong to our app
        isActive: false,              // Is the panel open?
        isNoteMode: false,            // Are we in "click to add note" mode?
        selectedElement: null,        // The element user clicked on
        currentNotes: [],             // All notes for this page
        noteClickHandler: null,       // Store our click handler so we can remove it later
        isDragging: false,            // Is the panel being dragged?
        dragOffset: { x: 0, y: 0 },   // Offset from mouse to panel corner during drag
        replyToNoteId: null,          // Track which note we're replying to
        expandedReplies: new Set(),  // Track which reply sections are expanded (collapsed by default)

        /**
         * INITIALIZATION
         * This runs when the page loads - it sets everything up
         */
        init: function() {
            // Validate that pageNotesData exists and has required properties
            if (typeof pageNotesData === 'undefined') {
                console.error('Page Notes: pageNotesData is not defined. Plugin may not be properly loaded.');
                return;
            }

            // Check for required properties
            const requiredProps = ['ajaxUrl', 'nonce', 'currentPageId', 'currentPageUrl', 'currentUserId', 'currentUserName'];
            const missingProps = requiredProps.filter(prop => !pageNotesData.hasOwnProperty(prop));

            if (missingProps.length > 0) {
                console.error('Page Notes: Missing required properties:', missingProps.join(', '));
                return;
            }

            // Create the HTML for our panel
            this.createPanel();

            // Set up all our event listeners (buttons, clicks, etc.)
            this.bindEvents();

            // Setup @mention autocomplete on the textarea
            const textarea = document.querySelector('.note-form-textarea');
            if (textarea) {
                this.setupMentionAutocomplete(textarea);
                this.setupCharacterCounter(textarea);
            }

            // Load the list of pages with notes
            this.loadPagesWithNotes();
        },
        
        /**
         * CREATE PANEL
         * This builds the HTML structure for our sticky note panel
         */
        createPanel: function() {
            // Create the main panel HTML as a string
            const panelHTML = `
                <div class="page-notes-panel">
                    <div class="page-notes-header">
                        <h3><span class="page-notes-drag-icon">⋮⋮</span>Page Notes</h3>
                        <div class="page-notes-header-actions">
                            <button class="page-notes-send-notifications" title="Send pending email notifications">📧 Send Notifications</button>
                            <button class="page-notes-close">&times;</button>
                        </div>
                    </div>
                    <div class="page-notes-content">
                        <!-- Pages with notes section -->
                        <div class="pages-with-notes">
                            <h4>Pages with Notes</h4>
                            <div class="pages-list"></div>
                        </div>
                        
                        <!-- Current page notes section -->
                        <div class="current-page-notes">
                            <h4>Notes on This Page</h4>
                            <div class="notes-list"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Modal for creating/editing notes -->
                <div class="note-form-overlay">
                    <div class="note-form">
                        <h3>Add Note</h3>
                        <div class="note-form-textarea-wrapper">
                            <textarea class="note-form-textarea" placeholder="Enter your note... Type @ to mention someone" maxlength="150"></textarea>
                            <div class="mention-autocomplete"></div>
                        </div>
                        <div class="note-form-char-counter">
                            <span class="char-count">0</span> / <span class="char-limit">150</span> characters
                        </div>
                        <div class="note-form-actions">
                            <button class="note-form-btn note-form-btn-cancel">Cancel</button>
                            <button class="note-form-btn note-form-btn-save">Save Note</button>
                        </div>
                    </div>
                </div>
                
                <!-- Mode indicator (shows when in "click to add note" mode) -->
                <div class="page-notes-mode-indicator">
                    Click any element to add a note
                </div>
            `;
            
            // Add the HTML to the page
            // In vanilla JS, we insert HTML like this:
            document.body.insertAdjacentHTML('beforeend', panelHTML);
        },
        
        /**
         * BIND EVENTS
         * This connects user actions (clicks, etc.) to our functions
         * Think of this like setting up "if user does X, then do Y"
         */
        bindEvents: function() {
            const self = this; // Save reference to PageNotes object
            
            // When user clicks the "Notes" button in admin bar
            // querySelector finds the first element matching the selector
            const toggleButton = document.querySelector('#wp-admin-bar-page-notes-toggle a');
            if (toggleButton) {
                toggleButton.addEventListener('click', function(e) {
                    e.preventDefault(); // Stop the link from doing its default action
                    self.togglePanel();
                });
            }
            
            // When user clicks the X button to close panel
            const closeButton = document.querySelector('.page-notes-close');
            if (closeButton) {
                closeButton.addEventListener('click', function() {
                    self.togglePanel();
                });
            }

            // When user clicks "Send Notifications" button
            const sendNotificationsButton = document.querySelector('.page-notes-send-notifications');
            if (sendNotificationsButton) {
                sendNotificationsButton.addEventListener('click', function() {
                    self.sendNotifications();
                });
            }
            
            // When user clicks "Save Note" in the form
            const saveButton = document.querySelector('.note-form-btn-save');
            if (saveButton) {
                saveButton.addEventListener('click', function() {
                    self.saveNote();
                });
            }
            
            // When user clicks "Cancel" in the form
            const cancelButton = document.querySelector('.note-form-btn-cancel');
            if (cancelButton) {
                cancelButton.addEventListener('click', function() {
                    self.closeNoteForm();
                });
            }
            
            // When user clicks outside the form modal, close it
            const overlay = document.querySelector('.note-form-overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    // e.target is the actual element that was clicked
                    // If they clicked the dark overlay (not the form itself), close it
                    if (e.target.classList.contains('note-form-overlay')) {
                        self.closeNoteForm();
                    }
                });
            }
            
            // EVENT DELEGATION: Instead of adding listeners to each dynamic element,
            // we listen on the document and check what was clicked.
            // This works even for elements created after page load!
            document.addEventListener('click', function(e) {
                // Check if a page item was clicked
                const pageItem = e.target.closest('.page-item');
                if (pageItem) {
                    const pageUrl = pageItem.getAttribute('data-url');
                    window.location.href = pageUrl + '?notes=active';
                    return;
                }
                
                // Check if edit button was clicked
                if (e.target.classList.contains('note-btn-edit')) {
                    e.stopPropagation(); // Don't trigger parent clicks
                    const noteItem = e.target.closest('.note-item');
                    const noteId = noteItem.getAttribute('data-note-id');
                    self.editNote(noteId);
                    return;
                }
                
                // Check if complete button was clicked
                if (e.target.classList.contains('note-btn-complete')) {
                    e.stopPropagation();
                    const noteItem = e.target.closest('.note-item');
                    const noteId = noteItem.getAttribute('data-note-id');
                    self.completeNote(noteId);
                    return;
                }
                
                // Check if delete button was clicked
                if (e.target.classList.contains('note-btn-delete')) {
                    e.stopPropagation();

                    // Prevent deletion if button is disabled
                    if (e.target.disabled) {
                        return;
                    }

                    const noteItem = e.target.closest('.note-item');
                    const noteId = noteItem.getAttribute('data-note-id');
                    self.deleteNote(noteId);
                    return;
                }

                // Check if reply button was clicked
                if (e.target.classList.contains('note-btn-reply')) {
                    e.stopPropagation();
                    const noteItem = e.target.closest('.note-item');
                    const noteId = noteItem.getAttribute('data-note-id');
                    self.replyToNote(noteId);
                    return;
                }

                // Check if toggle replies button was clicked
                const toggleButton = e.target.closest('.note-replies-toggle');
                if (toggleButton) {
                    e.stopPropagation();
                    const noteId = toggleButton.getAttribute('data-note-id');
                    self.toggleReplies(noteId);
                    return;
                }
            });
            
            // Mouse hover events for highlighting elements
            document.addEventListener('mouseenter', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('note-item')) {
                    const selector = e.target.getAttribute('data-selector');
                    self.highlightElement(selector);
                }
            }, true); // 'true' means capture phase (catches events earlier)
            
            document.addEventListener('mouseleave', function(e) {
                if (e.target && e.target.classList && e.target.classList.contains('note-item')) {
                    self.removeHighlight();
                }
            }, true);
            
            // Check if we should auto-open notes (from URL parameter)
            if (window.location.search.includes('notes=active')) {
                this.togglePanel();
            }

            // Initialize drag functionality
            this.initDrag();
        },

        /**
         * INITIALIZE DRAG
         * Makes the panel draggable by its header
         */
        initDrag: function() {
            const self = this;
            const panel = document.querySelector('.page-notes-panel');
            const header = document.querySelector('.page-notes-header');

            if (!panel || !header) return;

            // Mouse down on header starts drag
            header.addEventListener('mousedown', function(e) {
                // Don't drag if clicking the close button
                if (e.target.classList.contains('page-notes-close')) return;

                self.isDragging = true;
                panel.classList.add('dragging');

                // Calculate offset from mouse to panel's top-left corner
                const rect = panel.getBoundingClientRect();
                self.dragOffset.x = e.clientX - rect.left;
                self.dragOffset.y = e.clientY - rect.top;

                e.preventDefault();
            });

            // Mouse move updates panel position
            document.addEventListener('mousemove', function(e) {
                if (!self.isDragging) return;

                // Calculate new position
                let newX = e.clientX - self.dragOffset.x;
                let newY = e.clientY - self.dragOffset.y;

                // Constrain to viewport
                const panelRect = panel.getBoundingClientRect();
                const maxX = window.innerWidth - panelRect.width;
                const maxY = window.innerHeight - panelRect.height;

                newX = Math.max(0, Math.min(newX, maxX));
                newY = Math.max(0, Math.min(newY, maxY));

                // Apply position
                panel.classList.add('positioned');
                panel.style.left = newX + 'px';
                panel.style.top = newY + 'px';
            });

            // Mouse up ends drag
            document.addEventListener('mouseup', function() {
                if (self.isDragging) {
                    self.isDragging = false;
                    panel.classList.remove('dragging');
                }
            });

            // Handle window resize - keep panel within viewport
            window.addEventListener('resize', function() {
                self.constrainPanelToViewport(panel);
            });
        },

        /**
         * CONSTRAIN PANEL TO VIEWPORT
         * Ensures the panel stays within visible screen boundaries
         */
        constrainPanelToViewport: function(panel) {
            // Only constrain if panel is active and visible
            if (!panel.classList.contains('active')) return;

            // If panel has been positioned (dragged), constrain its custom position
            if (panel.classList.contains('positioned')) {
                const rect = panel.getBoundingClientRect();

                // Calculate maximum allowed positions
                const maxX = window.innerWidth - rect.width;
                const maxY = window.innerHeight - rect.height;

                // Get current position from the actual rendered position
                let newLeft = rect.left;
                let newTop = rect.top;

                // Constrain horizontally
                if (newLeft < 0) newLeft = 0;
                if (newLeft > maxX) newLeft = Math.max(0, maxX);

                // Constrain vertically
                if (newTop < 0) newTop = 0;
                if (newTop > maxY) newTop = Math.max(0, maxY);

                // Apply constrained position
                panel.style.left = newLeft + 'px';
                panel.style.top = newTop + 'px';
            }
            // If panel is in default position (right side), no constraint needed
            // CSS will handle it automatically
        },

        /**
         * TOGGLE PANEL
         * Opens or closes the notes panel
         */
        togglePanel: function() {
            this.isActive = !this.isActive;

            const panel = document.querySelector('.page-notes-panel');
            const adminBarButton = document.querySelector('#wp-admin-bar-page-notes-toggle');

            // classList.toggle() adds the class if it's not there, removes it if it is
            if (this.isActive) {
                // Make sure panel is visible (in case it was hidden when positioned)
                panel.style.display = 'flex';
                panel.classList.add('active');
                if (adminBarButton) adminBarButton.classList.add('active');
                // Panel just opened - load notes for current page
                this.loadCurrentPageNotes();
                // Check for pending notifications and update button visibility
                this.updateNotificationButtonVisibility();
                // Enter "note mode" where user can click elements
                this.enableNoteMode();
            } else {
                panel.classList.remove('active');
                if (adminBarButton) adminBarButton.classList.remove('active');
                // Panel closed - disable note mode
                this.disableNoteMode();

                // If panel was positioned (dragged), hide it completely
                if (panel.classList.contains('positioned')) {
                    panel.style.display = 'none';
                }
            }
        },
        
        /**
         * ENABLE NOTE MODE
         * Allows users to click any element to add a note
         */
        enableNoteMode: function() {
            this.isNoteMode = true;
            const self = this;
            
            // Show the indicator
            document.querySelector('.page-notes-mode-indicator').classList.add('active');
            
            // Create the click handler function
            // We save it so we can remove it later
            this.noteClickHandler = function(e) {
                // Only respond if we're in note mode
                if (!self.isNoteMode) return;
                
                // Don't capture clicks on the panel itself or admin bar
                const panel = e.target.closest('.page-notes-panel');
                const adminBar = e.target.closest('#wpadminbar');
                const overlay = e.target.closest('.note-form-overlay');
                
                if (panel || adminBar || overlay) return;
                
                e.preventDefault(); // Don't let the click do its normal thing
                e.stopPropagation(); // Don't let the click bubble up to parent elements
                
                self.selectedElement = e.target; // Save which element was clicked
                self.openNoteForm(); // Show the form to create a note
            };
            
            // Add the click handler to the document
            // We use capture phase (true) to catch the event before other handlers
            document.addEventListener('click', this.noteClickHandler, true);
        },
        
        /**
         * DISABLE NOTE MODE
         * Turns off the click-to-add-note functionality
         */
        disableNoteMode: function() {
            this.isNoteMode = false;
            
            // Hide the indicator
            document.querySelector('.page-notes-mode-indicator').classList.remove('active');
            
            // Remove the click handler
            if (this.noteClickHandler) {
                document.removeEventListener('click', this.noteClickHandler, true);
                this.noteClickHandler = null;
            }
        },
        
        /**
         * OPEN NOTE FORM
         * Shows the modal form for creating a note
         */
        openNoteForm: function(isReply = false, shouldQuote = false, parentNote = null) {
            const overlay = document.querySelector('.note-form-overlay');
            const textarea = document.querySelector('.note-form-textarea');
            const formTitle = document.querySelector('.note-form h3');

            // Clear the textarea by default
            textarea.value = '';

            if (isReply && this.replyToNoteId) {
                const replyingToNote = parentNote || this.currentNotes.find(n => n.id == this.replyToNoteId);
                if (replyingToNote) {
                    formTitle.textContent = `Reply to ${replyingToNote.user_name}`;

                    // Add quoted text if needed (replying to older messages)
                    if (shouldQuote) {
                        const quotedText = this.generateQuotedText(replyingToNote);
                        textarea.value = quotedText + '\n\n';
                        // Move cursor to end after the quoted text
                        setTimeout(() => {
                            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
                        }, 0);
                    }
                }
            } else {
                formTitle.textContent = 'Add Note';
            }

            // Update character counter
            textarea.dispatchEvent(new Event('input'));

            overlay.classList.add('active');
            textarea.focus(); // Put cursor in the textarea
        },

        /**
         * GENERATE QUOTED TEXT
         * Creates a quoted version of a note's content for replying
         */
        generateQuotedText: function(note) {
            // Strip @mentions from the content
            const content = this.stripMentions(note.content);

            // Truncate to approximately 2 lines (about 120 characters)
            const truncated = this.truncateText(content, 120);

            // Format as quote
            const quoteLine = `> ${note.user_name} wrote:`;
            const quoteContent = truncated.split('\n').map(line => `> ${line}`).join('\n');

            return `${quoteLine}\n${quoteContent}`;
        },

        /**
         * TRUNCATE TEXT
         * Truncates text to a maximum length, adding ellipsis if needed
         */
        truncateText: function(text, maxLength) {
            if (!text) return '';

            // Clean up whitespace
            text = text.trim();

            if (text.length <= maxLength) {
                return text;
            }

            // Find a good breaking point (space, period, comma)
            let truncated = text.substring(0, maxLength);
            const lastSpace = truncated.lastIndexOf(' ');
            const lastPeriod = truncated.lastIndexOf('.');
            const lastComma = truncated.lastIndexOf(',');

            const breakPoint = Math.max(lastSpace, lastPeriod, lastComma);

            if (breakPoint > maxLength * 0.7) {
                // Use the break point if it's not too far back
                truncated = text.substring(0, breakPoint);
            }

            return truncated.trim() + '...';
        },

        /**
         * FORMAT QUOTED CONTENT
         * Converts > quoted lines into HTML blockquotes with proper styling
         */
        formatQuotedContent: function(content) {
            if (!content) return '';

            // Decode HTML entities first (in case content has &gt; instead of >)
            const decodedContent = this.decodeHtml(content);

            const lines = decodedContent.split('\n');
            let html = '';
            let inQuote = false;
            let quoteLines = [];

            for (let i = 0; i < lines.length; i++) {
                const line = lines[i];

                if (line.trim().startsWith('>')) {
                    // This is a quoted line
                    if (!inQuote) {
                        inQuote = true;
                        quoteLines = [];
                    }
                    // Remove the > and add to quote
                    quoteLines.push(line.replace(/^>\s?/, ''));
                } else {
                    // Not a quoted line
                    if (inQuote) {
                        // End the quote block and format it
                        html += this.formatQuoteBlock(quoteLines);
                        inQuote = false;
                        quoteLines = [];
                    }
                    // Add the regular line
                    if (line.trim()) {
                        html += this.escapeHtml(line) + '\n';
                    } else {
                        html += '\n';
                    }
                }
            }

            // Close any remaining quote
            if (inQuote && quoteLines.length > 0) {
                html += this.formatQuoteBlock(quoteLines);
            }

            return html.trim();
        },

        /**
         * FORMAT QUOTE BLOCK
         * Formats an array of quote lines into a styled blockquote
         */
        formatQuoteBlock: function(quoteLines) {
            if (!quoteLines || quoteLines.length === 0) return '';

            // Check if first line is the "wrote:" header
            const firstLine = quoteLines[0];
            let authorLine = '';
            let contentLines = quoteLines;

            if (firstLine.includes(' wrote:')) {
                authorLine = '<div class="quote-author">' + this.escapeHtml(firstLine) + '</div>';
                contentLines = quoteLines.slice(1);
            }

            const content = contentLines.map(line => this.escapeHtml(line)).join('\n');

            return '<blockquote class="note-quote">' +
                   '<span class="quote-mark">"</span>' +
                   '<div class="quote-content">' +
                   authorLine +
                   (content ? '<div class="quote-text">' + content + '</div>' : '') +
                   '</div>' +
                   '</blockquote>';
        },

        /**
         * ESCAPE HTML
         * Escapes HTML special characters to prevent XSS
         */
        escapeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * DECODE HTML
         * Decodes HTML entities back to regular characters
         */
        decodeHtml: function(text) {
            const textarea = document.createElement('textarea');
            textarea.innerHTML = text;
            return textarea.value;
        },

        /**
         * CLOSE NOTE FORM
         * Hides the modal form
         */
        closeNoteForm: function() {
            document.querySelector('.note-form-overlay').classList.remove('active');
            document.querySelector('.note-form h3').textContent = 'Add Note';
            this.selectedElement = null;
            this.replyToNoteId = null;
        },
        
        /**
         * SAVE NOTE
         * Sends the note to the server via AJAX
         */
        saveNote: function() {
            const content = document.querySelector('.note-form-textarea').value.trim();

            // Validate that user entered something
            if (!content) {
                alert('Please enter a note');
                return;
            }

            // If this is a reply, we don't need an element selector
            let selector = '';
            if (this.replyToNoteId) {
                // For replies, use the parent note's selector
                const parentNote = this.currentNotes.find(n => n.id == this.replyToNoteId);
                if (parentNote) {
                    selector = parentNote.element_selector;
                }
            } else {
                // Make sure an element was selected for new notes
                if (!this.selectedElement) {
                    alert('No element selected');
                    return;
                }
                // Generate a unique CSS selector for the element
                selector = this.generateSelector(this.selectedElement);
            }

            // Prepare the data to send to server
            // FormData is a special object for sending form data
            const formData = new FormData();
            formData.append('action', 'pn_save_note'); // WordPress needs to know which function to call
            formData.append('nonce', pageNotesData.nonce); // Security token
            formData.append('page_id', pageNotesData.currentPageId);
            formData.append('page_url', pageNotesData.currentPageUrl);
            formData.append('element_selector', selector);
            formData.append('content', content);

            // Add parent_id if this is a reply
            if (this.replyToNoteId) {
                formData.append('parent_id', this.replyToNoteId);
            }
            
            // AJAX CALL using Fetch API (modern JavaScript way)
            // fetch() sends a request to the server
            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin' // Include cookies
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                // This runs when we get the response
                if (data.success) {
                    // Note saved successfully!
                    this.closeNoteForm();

                    // Store the new note ID to highlight it after reload
                    const newNoteId = data.data.id;

                    this.loadCurrentPageNotes().then(() => {
                        // After notes are loaded, highlight the new one
                        this.highlightNewNote(newNoteId);
                    });
                    this.loadPagesWithNotes();
                    // Update notification button visibility (new note might have @mention)
                    this.updateNotificationButtonVisibility();
                } else {
                    // Something went wrong - show the error message from server
                    const errorMsg = data.data || 'Unknown error occurred';
                    alert('Error saving note: ' + errorMsg);
                }
            })
            .catch(error => {
                // This runs if there's a network error
                console.error('Error:', error);
                alert('Failed to save note. Please check your connection and try again.');
            });
        },
        
        /**
         * GENERATE SELECTOR
         * Creates a unique CSS selector for an element with maximum stability
         * Optimized for WordPress blocks and page builders (Elementor, Beaver Builder, Divi, etc.)
         */
        generateSelector: function(element) {
            // Strategy 1: Element has a unique ID
            if (element.id) {
                const selector = '#' + CSS.escape(element.id);
                if (this.validateSelector(selector, element)) {
                    return selector;
                }
            }

            // Strategy 2: WordPress Block Editor - Check for block-level data attributes
            // Gutenberg blocks often have data-block, data-type, or unique IDs on wrapper divs
            const blockSelectors = this.tryWordPressBlockSelector(element);
            if (blockSelectors) {
                return blockSelectors;
            }

            // Strategy 3: Page Builder specific attributes (Elementor, Beaver Builder, Divi)
            const builderSelector = this.tryPageBuilderSelector(element);
            if (builderSelector) {
                return builderSelector;
            }

            // Strategy 4: Look for closest parent with stable ID, then build relative path
            const parentIdSelector = this.tryParentIdSelector(element);
            if (parentIdSelector) {
                return parentIdSelector;
            }

            // Strategy 5: Use stable data attributes on the element itself
            const dataSelector = this.tryDataAttributeSelector(element);
            if (dataSelector) {
                return dataSelector;
            }

            // Strategy 6: Use stable CSS classes (avoiding dynamic ones)
            const classSelector = this.tryStableClassSelector(element);
            if (classSelector) {
                return classSelector;
            }

            // Strategy 7: LAST RESORT - Add our own permanent data attribute
            // This is the most stable option as it persists with the element
            const uniqueId = 'pn-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            element.setAttribute('data-page-note-id', uniqueId);
            return `[data-page-note-id="${uniqueId}"]`;
        },

        /**
         * Try to create selector using WordPress block editor attributes
         */
        tryWordPressBlockSelector: function(element) {
            // Check element itself and up to 5 parents for block attributes
            let current = element;
            let depth = 0;
            const maxDepth = 5;

            while (current && current !== document.body && depth < maxDepth) {
                // WordPress blocks often have wp-block-* classes
                if (current.className && typeof current.className === 'string') {
                    const blockClasses = current.className.split(' ')
                        .filter(c => c.startsWith('wp-block-'));

                    if (blockClasses.length > 0) {
                        // Found a block wrapper, build selector from here
                        const blockSelector = current.tagName.toLowerCase() + '.' +
                            blockClasses.map(c => CSS.escape(c)).join('.');

                        // If we're selecting the block itself
                        if (current === element && this.validateSelector(blockSelector, element)) {
                            return blockSelector;
                        }

                        // If we're selecting a child of the block, build path from block
                        if (current !== element) {
                            const childPath = this.buildPathFromAncestor(element, current);
                            if (childPath) {
                                const fullSelector = blockSelector + ' ' + childPath;
                                if (this.validateSelector(fullSelector, element)) {
                                    return fullSelector;
                                }
                            }
                        }
                    }
                }

                // Check for WordPress block data attributes
                if (current.hasAttribute('data-block')) {
                    const blockId = current.getAttribute('data-block');
                    const selector = `[data-block="${CSS.escape(blockId)}"]`;

                    if (current === element && this.validateSelector(selector, element)) {
                        return selector;
                    }

                    if (current !== element) {
                        const childPath = this.buildPathFromAncestor(element, current);
                        if (childPath) {
                            const fullSelector = selector + ' ' + childPath;
                            if (this.validateSelector(fullSelector, element)) {
                                return fullSelector;
                            }
                        }
                    }
                }

                current = current.parentElement;
                depth++;
            }

            return null;
        },

        /**
         * Try to create selector using page builder attributes
         */
        tryPageBuilderSelector: function(element) {
            // Common page builder attributes to look for
            const builderAttrs = [
                'data-id',           // Elementor
                'data-element_type', // Elementor
                'data-widget_type',  // Elementor
                'data-node',         // Beaver Builder
                'data-bb-id',        // Beaver Builder
                'data-et-multi-view', // Divi
                'data-et-id',        // Divi
                'data-vc-',          // WPBakery (multiple attributes start with this)
            ];

            let current = element;
            let depth = 0;
            const maxDepth = 5;

            while (current && current !== document.body && depth < maxDepth) {
                for (const attr of builderAttrs) {
                    if (attr.endsWith('-')) {
                        // Prefix match (like data-vc-)
                        const matchingAttrs = Array.from(current.attributes)
                            .filter(a => a.name.startsWith(attr));

                        if (matchingAttrs.length > 0) {
                            const selector = element.tagName.toLowerCase() +
                                matchingAttrs.slice(0, 2).map(a =>
                                    `[${a.name}="${CSS.escape(a.value)}"]`
                                ).join('');

                            if (this.validateSelector(selector, element)) {
                                return selector;
                            }
                        }
                    } else {
                        // Exact attribute match
                        if (current.hasAttribute(attr)) {
                            const value = current.getAttribute(attr);
                            const selector = `[${attr}="${CSS.escape(value)}"]`;

                            if (current === element && this.validateSelector(selector, element)) {
                                return selector;
                            }

                            if (current !== element) {
                                const childPath = this.buildPathFromAncestor(element, current);
                                if (childPath) {
                                    const fullSelector = selector + ' ' + childPath;
                                    if (this.validateSelector(fullSelector, element)) {
                                        return fullSelector;
                                    }
                                }
                            }
                        }
                    }
                }

                current = current.parentElement;
                depth++;
            }

            return null;
        },

        /**
         * Try to find a parent with an ID and build path from there
         */
        tryParentIdSelector: function(element) {
            let current = element.parentElement;
            let depth = 0;
            const maxDepth = 8;

            while (current && current !== document.body && depth < maxDepth) {
                if (current.id) {
                    const parentSelector = '#' + CSS.escape(current.id);
                    const childPath = this.buildPathFromAncestor(element, current);

                    if (childPath) {
                        const fullSelector = parentSelector + ' ' + childPath;
                        if (this.validateSelector(fullSelector, element)) {
                            return fullSelector;
                        }
                    }
                }

                current = current.parentElement;
                depth++;
            }

            return null;
        },

        /**
         * Build a stable path from ancestor to element
         * Avoids nth-child when possible, uses classes and attributes
         */
        buildPathFromAncestor: function(element, ancestor) {
            const path = [];
            let current = element;
            let maxDepth = 6;

            while (current && current !== ancestor && maxDepth > 0) {
                let segment = current.tagName.toLowerCase();

                // Try to add identifying features without nth-child
                if (current.id) {
                    segment = '#' + CSS.escape(current.id);
                    path.unshift(segment);
                    break; // ID is unique enough
                } else if (current.className && typeof current.className === 'string') {
                    const stableClasses = current.className
                        .split(' ')
                        .filter(c => c.trim())
                        .filter(c => !(/^(is-|has-|active|hover|focus|selected|current)/.test(c)))
                        .filter(c => !/\d{5,}/.test(c))
                        .slice(0, 2);

                    if (stableClasses.length > 0) {
                        segment += '.' + stableClasses.map(c => CSS.escape(c)).join('.');
                    }
                }

                path.unshift(segment);
                current = current.parentElement;
                maxDepth--;
            }

            return path.length > 0 ? path.join(' > ') : null;
        },

        /**
         * Try data attribute selector
         */
        tryDataAttributeSelector: function(element) {
            const dataAttrs = Array.from(element.attributes)
                .filter(attr => attr.name.startsWith('data-') &&
                               !attr.name.startsWith('data-page-note-')) // Don't use our own attributes
                .slice(0, 2);

            if (dataAttrs.length > 0) {
                const selector = element.tagName.toLowerCase() +
                    dataAttrs.map(attr => `[${attr.name}="${CSS.escape(attr.value)}"]`).join('');
                if (this.validateSelector(selector, element)) {
                    return selector;
                }
            }

            return null;
        },

        /**
         * Try stable class selector
         */
        tryStableClassSelector: function(element) {
            if (element.className && typeof element.className === 'string') {
                const stableClasses = element.className
                    .split(' ')
                    .filter(c => c.trim())
                    .filter(c => !(/^(wp-|is-|has-|active|hover|focus|selected|current|et-|vc-|fl-)/.test(c)))
                    .filter(c => !/\d{5,}/.test(c))
                    .slice(0, 3);

                if (stableClasses.length > 0) {
                    const selector = element.tagName.toLowerCase() + '.' +
                        stableClasses.map(c => CSS.escape(c)).join('.');
                    if (this.validateSelector(selector, element)) {
                        return selector;
                    }
                }
            }

            return null;
        },

        /**
         * VALIDATE SELECTOR
         * Checks if a selector uniquely identifies the target element
         */
        validateSelector: function(selector, targetElement) {
            try {
                const elements = document.querySelectorAll(selector);
                // Selector must match exactly one element, and it must be our target
                return elements.length === 1 && elements[0] === targetElement;
            } catch (e) {
                // Invalid selector syntax
                console.warn('Invalid selector:', selector, e);
                return false;
            }
        },
        
        /**
         * LOAD PAGES WITH NOTES
         * Gets the list of all pages that have notes
         */
        loadPagesWithNotes: function() {
            const formData = new FormData();
            formData.append('action', 'pn_get_pages_with_notes');
            formData.append('nonce', pageNotesData.nonce);
            
            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    this.renderPagesList(data.data);
                } else {
                    const pagesList = document.querySelector('.pages-list');
                    pagesList.innerHTML = '<div class="empty-state"><div class="empty-state-text">No pages with notes yet</div></div>';
                }
            })
            .catch(error => {
                console.error('Error loading pages:', error);
            });
        },
        
        /**
         * RENDER PAGES LIST
         * Displays the list of pages with notes in the panel
         */
        renderPagesList: function(pages) {
            let html = '';

            // Loop through each page and create HTML for it
            // forEach is like a "for each item in this array, do something"
            pages.forEach(page => {
                const openCount = parseInt(page.open_count) || 0;
                const totalCount = parseInt(page.note_count) || 0;

                // Add 'all-completed' class if all notes are completed (open count is 0)
                const completedClass = (openCount === 0 && totalCount > 0) ? 'all-completed' : '';

                // Only show count pill if there are open notes
                // If all completed, no pill is shown (green background indicates completion)
                const countPill = openCount > 0
                    ? `<span class="page-item-count">${openCount}</span>`
                    : '';

                html += `
                    <div class="page-item ${completedClass}" data-url="${page.page_url}">
                        <span class="page-item-title">${page.page_title}</span>
                        ${countPill}
                    </div>
                `;
            });

            // Insert the HTML into the pages list container
            document.querySelector('.pages-list').innerHTML = html;
        },
        
        /**
         * LOAD CURRENT PAGE NOTES
         * Gets all notes for the page we're currently on
         * Returns a Promise so we can chain actions after loading
         */
        loadCurrentPageNotes: function() {
            const formData = new FormData();
            formData.append('action', 'pn_get_notes');
            formData.append('nonce', pageNotesData.nonce);
            formData.append('page_id', pageNotesData.currentPageId);
            formData.append('page_url', pageNotesData.currentPageUrl);

            return fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.currentNotes = data.data;
                    this.renderNotesList(data.data);
                }
            })
            .catch(error => {
                console.error('Error loading notes:', error);
            });
        },
        
        /**
         * RENDER NOTES LIST
         * Displays all notes in the panel with threaded replies
         */
        renderNotesList: function(notes) {
            const notesList = document.querySelector('.notes-list');

            if (notes.length === 0) {
                notesList.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📝</div><div class="empty-state-text">No notes on this page yet.<br>Click any element to add a note!</div></div>';
                return;
            }

            // Filter to show only parent notes
            const parentNotes = notes.filter(note => note.parent_id == 0);

            // Sort notes: open notes first, then completed notes
            // Within each group, newest notes appear first
            parentNotes.sort((a, b) => {
                // If one is open and one is completed, open comes first
                if (a.status === 'open' && b.status === 'completed') return -1;
                if (a.status === 'completed' && b.status === 'open') return 1;
                // If both have the same status, sort by creation date (newest first)
                const dateA = new Date(a.created_at);
                const dateB = new Date(b.created_at);
                return dateB - dateA; // Newest first
            });

            let html = '';
            parentNotes.forEach(note => {
                html += this.renderNote(note, notes);
            });

            notesList.innerHTML = html;
        },

        /**
         * RENDER NOTE
         * Renders a single note with its replies
         */
        renderNote: function(note, allNotes) {
            const completedClass = note.status === 'completed' ? 'completed' : '';
            const contextOnlyClass = note.is_context_only ? 'context-only' : '';
            const date = new Date(note.created_at).toLocaleDateString();

            // Show assignment badge if note is assigned
            const assignedBadge = note.assigned_to && note.assigned_to_name
                ? `<div class="note-assigned-badge">Assigned to: ${note.assigned_to_name}</div>`
                : '';

            // Show context indicator for notes shown only for threading context
            const contextBadge = note.is_context_only
                ? `<div class="note-context-badge" title="This note is shown for context only">Context</div>`
                : '';

            // Strip @mentions from content since we show assignment separately
            const strippedContent = this.stripMentions(note.content);
            // Format quoted content (convert > lines to blockquotes)
            const displayContent = this.formatQuotedContent(strippedContent);

            // Get ALL replies in this thread (any depth) - we'll show them linearly
            const replies = this.getAllRepliesInThread(note.id, allNotes);
            replies.sort((a, b) => new Date(a.created_at) - new Date(b.created_at)); // Chronological order

            // Build reply count toggle
            let replyToggle = '';
            let repliesHtml = '';
            if (replies.length > 0) {
                const isExpanded = this.expandedReplies.has(parseInt(note.id));
                const toggleIcon = isExpanded ? '▼' : '▶';
                const toggleText = replies.length === 1 ? '1 reply' : `${replies.length} replies`;

                replyToggle = `
                    <button class="note-replies-toggle" data-note-id="${note.id}">
                        <span class="toggle-icon">${toggleIcon}</span> ${toggleText}
                    </button>
                `;

                // Build replies HTML - all stacked linearly
                if (isExpanded) {
                    repliesHtml = '<div class="note-replies">';
                    replies.forEach(reply => {
                        const replyDate = new Date(reply.created_at).toLocaleDateString();
                        const replyStripped = this.stripMentions(reply.content);
                        const replyContent = this.formatQuotedContent(replyStripped);
                        const replyCompletedClass = reply.status === 'completed' ? 'completed' : '';

                        // If this is a reply to another reply, show "replying to [Name]" label
                        let replyingToLabel = '';
                        if (reply.parent_id != note.id) {
                            // This is a nested reply - find the parent
                            const parentReply = allNotes.find(n => n.id == reply.parent_id);
                            if (parentReply) {
                                replyingToLabel = `<div class="replying-to-label">replying to ${parentReply.user_name}</div>`;
                            }
                        }

                        // Check if this reply has replies of its own
                        const replyHasReplies = this.getAllRepliesInThread(reply.id, allNotes).length > 0;
                        const replyDeleteBtn = replyHasReplies
                            ? '<button class="note-btn note-btn-delete" disabled title="Cannot delete - this note has replies">Delete</button>'
                            : '<button class="note-btn note-btn-delete">Delete</button>';

                        // Check if current user owns this reply
                        const replyIsOwnedByCurrentUser = reply.user_id == pageNotesData.currentUserId;

                        // Context-only replies: hide action buttons, add badge
                        const replyContextOnlyClass = reply.is_context_only ? 'context-only' : '';
                        const replyContextBadge = reply.is_context_only
                            ? `<div class="note-context-badge" title="This note is shown for context only">Context</div>`
                            : '';

                        // Build action buttons - only show Edit and Delete for note owner
                        let replyActions = '';
                        if (!reply.is_context_only) {
                            const editBtn = replyIsOwnedByCurrentUser ? '<button class="note-btn note-btn-edit">Edit</button>' : '';
                            const deleteButton = replyIsOwnedByCurrentUser ? replyDeleteBtn : '';

                            replyActions = `
                                <div class="note-actions">
                                    <button class="note-btn note-btn-reply">Reply</button>
                                    ${editBtn}
                                    <button class="note-btn note-btn-complete">${reply.status === 'completed' ? 'Reopen' : 'Complete'}</button>
                                    ${deleteButton}
                                </div>
                            `;
                        }

                        repliesHtml += `
                            <div class="note-item note-reply ${replyCompletedClass} ${replyContextOnlyClass}" data-note-id="${reply.id}" data-selector="${reply.element_selector}" data-parent-id="${reply.parent_id}">
                                ${replyingToLabel}
                                <div class="note-header">
                                    <span class="note-author">${reply.user_name}</span>
                                    <span class="note-date">${replyDate}</span>
                                    ${replyContextBadge}
                                </div>
                                <div class="note-content">${replyContent}</div>
                                ${replyActions}
                            </div>
                        `;
                    });
                    repliesHtml += '</div>';
                }
            }

            // Disable delete button if note has replies
            const hasReplies = replies.length > 0;
            const deleteBtn = hasReplies
                ? '<button class="note-btn note-btn-delete" disabled title="Cannot delete - this note has replies">Delete</button>'
                : '<button class="note-btn note-btn-delete">Delete</button>';

            // Check if current user owns this note
            const noteIsOwnedByCurrentUser = note.user_id == pageNotesData.currentUserId;

            // Build action buttons - only show Edit and Delete for note owner
            let noteActions = '';
            if (!note.is_context_only) {
                const editBtn = noteIsOwnedByCurrentUser ? '<button class="note-btn note-btn-edit">Edit</button>' : '';
                const deleteButton = noteIsOwnedByCurrentUser ? deleteBtn : '';

                noteActions = `
                    <div class="note-actions">
                        <button class="note-btn note-btn-reply">Reply</button>
                        ${editBtn}
                        <button class="note-btn note-btn-complete">${note.status === 'completed' ? 'Reopen' : 'Complete'}</button>
                        ${deleteButton}
                    </div>
                `;
            }

            return `
                <div class="note-item ${completedClass} ${contextOnlyClass}" data-note-id="${note.id}" data-selector="${note.element_selector}">
                    <div class="note-header">
                        <span class="note-author">${note.user_name}</span>
                        <span class="note-date">${date}</span>
                        ${contextBadge}
                    </div>
                    <div class="note-content">${displayContent}</div>
                    ${assignedBadge}
                    ${noteActions}
                    ${replyToggle}
                    ${repliesHtml}
                </div>
            `;
        },

        /**
         * Get all replies in a thread (recursively finds nested replies)
         * Returns a flat array of all replies at any depth
         */
        getAllRepliesInThread: function(parentNoteId, allNotes) {
            const replies = [];
            const directReplies = allNotes.filter(n => n.parent_id == parentNoteId);

            directReplies.forEach(reply => {
                replies.push(reply);
                // Recursively get replies to this reply
                const nestedReplies = this.getAllRepliesInThread(reply.id, allNotes);
                replies.push(...nestedReplies);
            });

            return replies;
        },
        
        /**
         * HIGHLIGHT ELEMENT
         * Highlights an element on the page and scrolls to it
         */
        highlightElement: function(selector) {
            try {
                // querySelector finds the first matching element
                const element = document.querySelector(selector);
                if (element) {
                    // Add highlight class
                    element.classList.add('page-notes-highlight');
                    
                    // Scroll to the element smoothly
                    // getBoundingClientRect() tells us where the element is on the page
                    const rect = element.getBoundingClientRect();
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    
                    window.scrollTo({
                        top: rect.top + scrollTop - 100,
                        behavior: 'smooth' // Makes it scroll smoothly instead of jumping
                    });
                }
            } catch (e) {
                console.log('Could not highlight element:', selector);
            }
        },
        
        /**
         * REMOVE HIGHLIGHT
         * Removes highlighting from all elements
         */
        removeHighlight: function() {
            // querySelectorAll finds ALL matching elements (returns an array-like object)
            const highlighted = document.querySelectorAll('.page-notes-highlight');
            highlighted.forEach(el => {
                el.classList.remove('page-notes-highlight');
            });
        },

        /**
         * HIGHLIGHT NEW NOTE
         * Adds a pulsing glow effect to a newly created note
         */
        highlightNewNote: function(noteId) {
            // Find the note element
            const noteElement = document.querySelector(`.note-item[data-note-id="${noteId}"]`);
            if (!noteElement) return;

            // Add the pulse class
            noteElement.classList.add('note-pulse');

            // Scroll to the note
            noteElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

            // Remove the pulse class after animation completes (3 seconds)
            setTimeout(() => {
                noteElement.classList.remove('note-pulse');
            }, 3000);
        },

        /**
         * EDIT NOTE
         * Opens form to edit an existing note
         */
        editNote: function(noteId) {
            // find() searches the array for the first item that matches
            const note = this.currentNotes.find(n => n.id == noteId);
            if (!note) return;

            // Pre-fill the form with existing content
            const formTitle = document.querySelector('.note-form h3');
            const textarea = document.querySelector('.note-form-textarea');
            const saveButton = document.querySelector('.note-form-btn-save');

            formTitle.textContent = 'Edit Note';
            textarea.value = note.content;
            document.querySelector('.note-form-overlay').classList.add('active');

            // Update character counter
            textarea.dispatchEvent(new Event('input'));

            // Change save button behavior to update instead of create
            // First, remove the old event listener by cloning the button
            const newSaveButton = saveButton.cloneNode(true);
            saveButton.parentNode.replaceChild(newSaveButton, saveButton);

            // Add new event listener for updating
            newSaveButton.addEventListener('click', () => {
                this.updateNote(noteId);
            });
        },
        
        /**
         * UPDATE NOTE
         * Saves changes to an existing note
         */
        updateNote: function(noteId) {
            const content = document.querySelector('.note-form-textarea').value.trim();
            
            if (!content) {
                alert('Please enter a note');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'pn_update_note');
            formData.append('nonce', pageNotesData.nonce);
            formData.append('note_id', noteId);
            formData.append('content', content);
            
            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.closeNoteForm();
                    this.loadCurrentPageNotes();

                    // Reset the form back to "Add Note" mode
                    document.querySelector('.note-form h3').textContent = 'Add Note';

                    // Reset the save button
                    const saveButton = document.querySelector('.note-form-btn-save');
                    const newSaveButton = saveButton.cloneNode(true);
                    saveButton.parentNode.replaceChild(newSaveButton, saveButton);

                    newSaveButton.addEventListener('click', () => {
                        this.saveNote();
                    });
                } else {
                    const errorMsg = data.data || 'Unknown error occurred';
                    alert('Error updating note: ' + errorMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update note. Please check your connection and try again.');
            });
        },
        
        /**
         * COMPLETE NOTE
         * Marks a note as completed (or reopens it)
         */
        completeNote: function(noteId) {
            const note = this.currentNotes.find(n => n.id == noteId);
            const newStatus = note.status === 'completed' ? 'open' : 'completed';
            
            const formData = new FormData();
            formData.append('action', 'pn_update_note');
            formData.append('nonce', pageNotesData.nonce);
            formData.append('note_id', noteId);
            formData.append('status', newStatus);
            formData.append('content', note.content); // Must include content even if not changing
            
            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.loadCurrentPageNotes();
                    this.loadPagesWithNotes(); // Update the pages list to reflect new counts
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        },
        
        /**
         * DELETE NOTE
         * Removes a note permanently
         */
        deleteNote: function(noteId) {
            // confirm() shows a dialog and returns true/false
            if (!confirm('Are you sure you want to delete this note?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'pn_delete_note');
            formData.append('nonce', pageNotesData.nonce);
            formData.append('note_id', noteId);

            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    this.loadCurrentPageNotes();
                    this.loadPagesWithNotes();
                } else {
                    const errorMsg = data.data || 'Unknown error occurred';
                    alert('Error deleting note: ' + errorMsg);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to delete note. Please check your connection and try again.');
            });
        },

        /**
         * REPLY TO NOTE
         * Opens the note form to reply to a specific note
         */
        replyToNote: function(noteId) {
            this.replyToNoteId = noteId;

            // Find the parent note we're replying to
            const parentNote = this.currentNotes.find(n => n.id == noteId);
            if (!parentNote) {
                this.openNoteForm(true);
                return;
            }

            // Get the root parent note (the main thread parent)
            let rootParentId = parentNote.parent_id || parentNote.id;
            if (parentNote.parent_id > 0) {
                // This is a reply, find the original parent
                let current = parentNote;
                while (current.parent_id > 0) {
                    const parent = this.currentNotes.find(n => n.id == current.parent_id);
                    if (!parent) break;
                    current = parent;
                }
                rootParentId = current.id;
            } else {
                rootParentId = parentNote.id;
            }

            // Get all replies in this thread
            const allThreadReplies = this.getAllRepliesInThread(rootParentId, this.currentNotes);

            // Sort by created_at to find the latest
            const sortedReplies = allThreadReplies.slice().sort((a, b) =>
                new Date(b.created_at) - new Date(a.created_at)
            );

            // Check if we're replying to the most recent message
            const latestReply = sortedReplies[0];
            const isReplyingToLatest = latestReply && latestReply.id == noteId;

            // If replying to an older message (not the latest), we'll need to quote
            const shouldQuote = !isReplyingToLatest && sortedReplies.length > 0;

            this.openNoteForm(true, shouldQuote, parentNote);
        },

        /**
         * TOGGLE REPLIES
         * Collapse or expand replies for a note
         */
        toggleReplies: function(noteId) {
            const noteIdNum = parseInt(noteId);

            if (this.expandedReplies.has(noteIdNum)) {
                this.expandedReplies.delete(noteIdNum);
            } else {
                this.expandedReplies.add(noteIdNum);
            }

            // Re-render the notes list to reflect the change
            this.renderNotesList(this.currentNotes);
        },

        /**
         * SETUP @MENTION AUTOCOMPLETE
         * Attaches autocomplete to a textarea for @mentions
         */
        setupMentionAutocomplete: function(textarea) {
            const self = this;
            const autocompleteDiv = document.querySelector('.mention-autocomplete');
            let currentSearch = '';
            let selectedIndex = -1;

            textarea.addEventListener('input', function(e) {
                const value = textarea.value;
                const cursorPos = textarea.selectionStart;

                // Find if there's an @ before the cursor
                const textBeforeCursor = value.substring(0, cursorPos);
                const match = textBeforeCursor.match(/@(\w*)$/);

                if (match) {
                    // User is typing a mention
                    currentSearch = match[1];
                    self.searchUsers(currentSearch, autocompleteDiv, textarea);
                } else {
                    // Hide autocomplete
                    autocompleteDiv.style.display = 'none';
                    selectedIndex = -1;
                }
            });

            // Handle keyboard navigation
            textarea.addEventListener('keydown', function(e) {
                const items = autocompleteDiv.querySelectorAll('.mention-item');

                if (items.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
                    self.updateSelection(items, selectedIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    selectedIndex = Math.max(selectedIndex - 1, 0);
                    self.updateSelection(items, selectedIndex);
                } else if (e.key === 'Enter' && selectedIndex >= 0 && autocompleteDiv.style.display !== 'none') {
                    e.preventDefault();
                    items[selectedIndex].click();
                } else if (e.key === 'Escape') {
                    autocompleteDiv.style.display = 'none';
                    selectedIndex = -1;
                }
            });
        },

        /**
         * SEARCH USERS
         * Search for users and display autocomplete results
         */
        searchUsers: function(search, autocompleteDiv, textarea) {
            const self = this;

            if (search.length === 0) {
                autocompleteDiv.style.display = 'none';
                return;
            }

            // AJAX call to search users
            const formData = new FormData();
            formData.append('action', 'pn_search_users');
            formData.append('nonce', pageNotesData.nonce);
            formData.append('search', search);

            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    self.displayUserResults(data.data, autocompleteDiv, textarea);
                } else {
                    autocompleteDiv.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error searching users:', error);
            });
        },

        /**
         * DISPLAY USER RESULTS
         * Show the autocomplete dropdown with user results
         */
        displayUserResults: function(users, autocompleteDiv, textarea) {
            const self = this;
            let html = '';

            users.forEach(function(user, index) {
                // Display format: "Display Name (username)"
                const displayText = user.display_name ? `${user.display_name} (@${user.username})` : `@${user.username}`;

                html += `
                    <div class="mention-item" data-username="${user.username}" data-index="${index}">
                        <span class="mention-name">${displayText}</span>
                    </div>
                `;
            });

            autocompleteDiv.innerHTML = html;
            autocompleteDiv.style.display = 'block';

            // Add click handlers to items
            const items = autocompleteDiv.querySelectorAll('.mention-item');
            items.forEach(function(item) {
                item.addEventListener('click', function() {
                    self.insertMention(item.getAttribute('data-username'), textarea, autocompleteDiv);
                });
            });
        },

        /**
         * INSERT MENTION
         * Insert the selected @mention into the textarea
         */
        insertMention: function(username, textarea, autocompleteDiv) {
            const value = textarea.value;
            const cursorPos = textarea.selectionStart;

            // Find the @ before cursor
            const textBeforeCursor = value.substring(0, cursorPos);
            const match = textBeforeCursor.match(/@(\w*)$/);

            if (match) {
                const mentionStart = cursorPos - match[0].length;
                const newValue = value.substring(0, mentionStart) + '@' + username + ' ' + value.substring(cursorPos);

                textarea.value = newValue;

                // Set cursor after the inserted mention
                const newCursorPos = mentionStart + username.length + 2; // +2 for @ and space
                textarea.setSelectionRange(newCursorPos, newCursorPos);
                textarea.focus();
            }

            // Hide autocomplete
            autocompleteDiv.style.display = 'none';
        },

        /**
         * UPDATE SELECTION
         * Highlight the selected item in autocomplete
         */
        updateSelection: function(items, selectedIndex) {
            items.forEach(function(item, index) {
                if (index === selectedIndex) {
                    item.classList.add('selected');
                } else {
                    item.classList.remove('selected');
                }
            });
        },

        /**
         * STRIP MENTIONS
         * Remove @mentions from displayed text since we show assignment badge
         */
        stripMentions: function(content) {
            // Remove @username mentions (username can have letters, numbers, underscore, hyphen)
            return content.replace(/@[\w-]+/g, '').trim();
        },

        /**
         * SETUP CHARACTER COUNTER
         * Initialize character counter for note textarea
         */
        setupCharacterCounter: function(textarea) {
            const charCountSpan = document.querySelector('.char-count');
            const charLimitSpan = document.querySelector('.char-limit');
            const counterDiv = document.querySelector('.note-form-char-counter');

            // Set the limit from PHP settings
            const characterLimit = pageNotesData.characterLimit || 150;
            if (charLimitSpan) {
                charLimitSpan.textContent = characterLimit;
            }

            // Set maxlength on textarea
            if (characterLimit > 0) {
                textarea.setAttribute('maxlength', characterLimit);
            } else {
                textarea.removeAttribute('maxlength');
            }

            // Hide counter if limit is 0 (no limit)
            if (characterLimit === 0 && counterDiv) {
                counterDiv.style.display = 'none';
                return;
            } else if (counterDiv) {
                counterDiv.style.display = '';
            }

            // Update counter on input
            const updateCounter = function() {
                const currentLength = textarea.value.length;

                if (charCountSpan) {
                    charCountSpan.textContent = currentLength;
                }

                // Add warning class when approaching limit (90% or more)
                if (counterDiv) {
                    if (currentLength >= characterLimit) {
                        counterDiv.classList.add('at-limit');
                        counterDiv.classList.remove('near-limit');
                    } else if (currentLength >= characterLimit * 0.9) {
                        counterDiv.classList.add('near-limit');
                        counterDiv.classList.remove('at-limit');
                    } else {
                        counterDiv.classList.remove('near-limit', 'at-limit');
                    }
                }
            };

            // Update on input
            textarea.addEventListener('input', updateCounter);

            // Initial update
            updateCounter();
        },

        /**
         * Check if there are pending notifications and update button visibility
         */
        updateNotificationButtonVisibility: function() {
            const button = document.querySelector('.page-notes-send-notifications');
            if (!button) return;

            // Make AJAX request to check for pending notifications
            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'pn_check_pending_notifications',
                    nonce: pageNotesData.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.has_pending) {
                    button.style.display = '';
                } else {
                    button.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error checking notifications:', error);
                // On error, hide the button to be safe
                button.style.display = 'none';
            });
        },

        /**
         * Send pending email notifications
         */
        sendNotifications: function() {
            const self = this;
            const button = document.querySelector('.page-notes-send-notifications');

            // Disable button during request
            if (button) {
                button.disabled = true;
                button.textContent = '📧 Sending...';
            }

            // Make AJAX request to send notifications
            fetch(pageNotesData.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'pn_send_notifications',
                    nonce: pageNotesData.nonce
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(data.data.message);

                    // Re-enable and update button visibility
                    if (button) {
                        button.disabled = false;
                        button.textContent = '📧 Send Notifications';
                    }

                    // Update button visibility after sending
                    self.updateNotificationButtonVisibility();
                } else {
                    alert('Error sending notifications: ' + (data.data || 'Unknown error'));
                    if (button) {
                        button.disabled = false;
                        button.textContent = '📧 Send Notifications';
                    }
                }
            })
            .catch(error => {
                console.error('Error sending notifications:', error);
                alert('Failed to send notifications. Please try again.');
                if (button) {
                    button.disabled = false;
                    button.textContent = '📧 Send Notifications';
                }
            });
        }
    };
    
    // ====================================================================
    // START THE APP
    // This runs when the document is fully loaded and ready
    // ====================================================================
    if (document.readyState === 'loading') {
        // If document is still loading, wait for it
        document.addEventListener('DOMContentLoaded', function() {
            PageNotes.init();
        });
    } else {
        // Document already loaded, initialize now
        PageNotes.init();
    }
    
})(); // This creates an IIFE (Immediately Invoked Function Expression)
      // It keeps our code contained and doesn't pollute the global scope