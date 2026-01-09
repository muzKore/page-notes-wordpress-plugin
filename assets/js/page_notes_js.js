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

        /**
         * INITIALIZATION
         * This runs when the page loads - it sets everything up
         */
        init: function() {
            console.log('Page Notes: Initializing...');

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
                        <button class="page-notes-close">&times;</button>
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
                        <textarea class="note-form-textarea" placeholder="Enter your note..."></textarea>
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
                    const noteItem = e.target.closest('.note-item');
                    const noteId = noteItem.getAttribute('data-note-id');
                    self.deleteNote(noteId);
                    return;
                }
            });
            
            // Mouse hover events for highlighting elements
            document.addEventListener('mouseenter', function(e) {
                if (e.target.classList.contains('note-item')) {
                    const selector = e.target.getAttribute('data-selector');
                    self.highlightElement(selector);
                }
            }, true); // 'true' means capture phase (catches events earlier)
            
            document.addEventListener('mouseleave', function(e) {
                if (e.target.classList.contains('note-item')) {
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
        openNoteForm: function() {
            const overlay = document.querySelector('.note-form-overlay');
            const textarea = document.querySelector('.note-form-textarea');
            
            overlay.classList.add('active');
            textarea.value = ''; // Clear the textarea
            textarea.focus(); // Put cursor in the textarea
        },
        
        /**
         * CLOSE NOTE FORM
         * Hides the modal form
         */
        closeNoteForm: function() {
            document.querySelector('.note-form-overlay').classList.remove('active');
            this.selectedElement = null;
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
            
            // Make sure an element was selected
            if (!this.selectedElement) {
                alert('No element selected');
                return;
            }
            
            // Generate a unique CSS selector for the element
            const selector = this.generateSelector(this.selectedElement);
            
            // Prepare the data to send to server
            // FormData is a special object for sending form data
            const formData = new FormData();
            formData.append('action', 'pn_save_note'); // WordPress needs to know which function to call
            formData.append('nonce', pageNotesData.nonce); // Security token
            formData.append('page_id', pageNotesData.currentPageId);
            formData.append('page_url', pageNotesData.currentPageUrl);
            formData.append('element_selector', selector);
            formData.append('content', content);
            
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
         * Creates a unique CSS selector for an element
         * This is how we "remember" which element a note is attached to
         */
        generateSelector: function(element) {
            // Strategy 1: If element has an ID, use that (IDs are unique)
            if (element.id) {
                const selector = '#' + CSS.escape(element.id);
                if (this.validateSelector(selector, element)) {
                    return selector;
                }
            }

            // Strategy 2: Try using data attributes (more stable than classes)
            const dataAttrs = Array.from(element.attributes)
                .filter(attr => attr.name.startsWith('data-'))
                .slice(0, 2); // Use up to 2 data attributes

            if (dataAttrs.length > 0) {
                const selector = element.tagName.toLowerCase() +
                    dataAttrs.map(attr => `[${attr.name}="${CSS.escape(attr.value)}"]`).join('');
                if (this.validateSelector(selector, element)) {
                    return selector;
                }
            }

            // Strategy 3: Use classes, but filter out dynamic/temporary ones
            if (element.className && typeof element.className === 'string') {
                // Filter out classes that look dynamic (contain numbers, hashes, etc.)
                const stableClasses = element.className
                    .split(' ')
                    .filter(c => c.trim())
                    .filter(c => !(/^(wp-|is-|has-|active|hover|focus|selected)/.test(c))) // Skip state classes
                    .filter(c => !/\d{5,}/.test(c)) // Skip classes with long numbers (likely generated)
                    .slice(0, 3); // Use max 3 classes

                if (stableClasses.length > 0) {
                    const selector = element.tagName.toLowerCase() + '.' +
                        stableClasses.map(c => CSS.escape(c)).join('.');
                    if (this.validateSelector(selector, element)) {
                        return selector;
                    }
                }
            }

            // Strategy 4: Build path from body using nth-of-type
            const path = [];
            let current = element;
            let maxDepth = 10; // Prevent infinite loops and overly long selectors

            while (current && current !== document.body && maxDepth > 0) {
                let selector = current.tagName.toLowerCase();

                // Get siblings of the same type
                const siblings = Array.from(current.parentElement.children)
                    .filter(el => el.tagName === current.tagName);

                if (siblings.length > 1) {
                    const index = siblings.indexOf(current) + 1;
                    selector += `:nth-of-type(${index})`;
                }

                path.unshift(selector);
                current = current.parentElement;
                maxDepth--;
            }

            const finalSelector = path.join(' > ');
            if (this.validateSelector(finalSelector, element)) {
                return finalSelector;
            }

            // Fallback: Generate a unique data attribute
            const uniqueId = 'pn-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            element.setAttribute('data-page-note-id', uniqueId);
            return `[data-page-note-id="${uniqueId}"]`;
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
         * Displays all notes in the panel
         */
        renderNotesList: function(notes) {
            const notesList = document.querySelector('.notes-list');

            if (notes.length === 0) {
                notesList.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📝</div><div class="empty-state-text">No notes on this page yet.<br>Click any element to add a note!</div></div>';
                return;
            }

            // Filter to show only parent notes (not replies - we'll add threading later)
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
                const completedClass = note.status === 'completed' ? 'completed' : '';
                // Create a Date object and format it nicely
                const date = new Date(note.created_at).toLocaleDateString();

                html += `
                    <div class="note-item ${completedClass}" data-note-id="${note.id}" data-selector="${note.element_selector}">
                        <div class="note-header">
                            <span class="note-author">${note.user_name}</span>
                            <span class="note-date">${date}</span>
                        </div>
                        <div class="note-content">${note.content}</div>
                        <div class="note-actions">
                            <button class="note-btn note-btn-edit">Edit</button>
                            <button class="note-btn note-btn-complete">${note.status === 'completed' ? 'Reopen' : 'Complete'}</button>
                            <button class="note-btn note-btn-delete">Delete</button>
                        </div>
                    </div>
                `;
            });

            notesList.innerHTML = html;
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