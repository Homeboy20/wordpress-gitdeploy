// GitHub Deployer Admin JavaScript

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Cache DOM elements
        const $form = $('#github-deployer-form');
        const $ownerInput = $('#github-deployer-owner');
        const $repoInput = $('#github-deployer-repo');
        const $refInput = $('#github-deployer-ref');
        const $typeSelect = $('#github-deployer-type');
        const $updateCheckbox = $('#github-deployer-update');
        const $autoUpdateCheckbox = $('#github-deployer-auto-update');
        const $repoInfo = $('#github-deployer-repo-info');
        const $repoInfoContent = $('#github-deployer-repo-info .content');
        const $repoInfoLoading = $('#github-deployer-repo-info .loading');
        const $submitButton = $('#github-deployer-submit');
        
        // Add event listeners
        $ownerInput.on('change', validateRepoInputs);
        $repoInput.on('change', validateRepoInputs);
        $typeSelect.on('change', checkForExisting);
        $autoUpdateCheckbox.on('change', updateSubmitButton);
        $updateCheckbox.on('change', function() {
            // If update mode is selected, show the auto-update option
            toggleAutoUpdateOption($(this).is(':checked'));
        });
        
        // Initial state
        toggleAutoUpdateOption(false);
        
        // Toggle auto-update option based on whether update mode is selected
        function toggleAutoUpdateOption(show) {
            if (show) {
                $('.auto-update-option').show();
            } else {
                $('.auto-update-option').hide();
                $autoUpdateCheckbox.prop('checked', false);
            }
            updateSubmitButton();
        }
        
        // Update the submit button text based on current options
        function updateSubmitButton() {
            if ($autoUpdateCheckbox.is(':checked')) {
                $submitButton.val(github_deployer.strings.deploy_and_track_button);
            } else if ($updateCheckbox.is(':checked')) {
                $submitButton.val(github_deployer.strings.update_button);
            } else {
                $submitButton.val(github_deployer.strings.deploy_button);
            }
        }
        
        // Helper function to validate repository inputs
        function validateRepoInputs() {
            const owner = $ownerInput.val().trim();
            const repo = $repoInput.val().trim();
            
            if (owner && repo) {
                fetchRepoInfo(owner, repo); // Fetches repo info, branches, tags, releases
                checkForExisting();
                // fetchRefs(owner, repo); // No longer need separate ref fetch
            } else {
                $repoInfo.hide();
                // Clear the select dropdown if inputs are empty
                $refInput.html('<option value="main" selected>' + github_deployer.strings.default_branch + '</option>'); 
            }
        }
        
        // Check if this plugin/theme already exists
        function checkForExisting() {
            const repo = $repoInput.val().trim();
            const type = $typeSelect.val();
            
            if (!repo) return;
            
            // AJAX request to check if plugin/theme exists
            $.ajax({
                url: github_deployer.ajax_url,
                type: 'GET',
                data: {
                    action: 'github_deployer_check_existing',
                    nonce: github_deployer.nonce,
                    repo: repo,
                    type: type
                },
                success: function(response) {
                    if (response.success && response.data.exists) {
                        // Show a notice that this will be an update
                        const noticeHtml = '<div class="update-notice"><p>' + 
                            (type === 'plugin' ? github_deployer.strings.plugin_exists : github_deployer.strings.theme_exists) + 
                            '</p></div>';
                        
                        if ($form.find('.update-notice').length === 0) {
                            $form.find('table.form-table').after(noticeHtml);
                        }
                        
                        // Check the update checkbox automatically
                        $updateCheckbox.prop('checked', true);
                        toggleAutoUpdateOption(true);
                        
                        // Change button text
                        updateSubmitButton();
                    } else {
                        // Remove notice if exists
                        $form.find('.update-notice').remove();
                        
                        // Reset button text
                        updateSubmitButton();
                    }
                }
            });
            
            // Also check if this repo is already tracked for auto-updates
            checkIfTracked(repo);
        }
        
        // Check if repository is already tracked for auto-updates
        function checkIfTracked(repo) {
            // AJAX request to check if repo is already tracked
            $.ajax({
                url: github_deployer.ajax_url,
                type: 'GET',
                data: {
                    action: 'github_deployer_check_tracked',
                    nonce: github_deployer.nonce,
                    repo: repo
                },
                success: function(response) {
                    if (response.success && response.data.tracked) {
                        const trackedHtml = '<div class="tracked-notice"><p>' + 
                            github_deployer.strings.repo_already_tracked + 
                            '</p></div>';
                        
                        if ($form.find('.tracked-notice').length === 0) {
                            $form.find('.auto-update-option').after(trackedHtml);
                        }
                        
                        // Disable the auto-update checkbox
                        $autoUpdateCheckbox.prop('checked', true).prop('disabled', true);
                    } else {
                        // Remove notice if exists
                        $form.find('.tracked-notice').remove();
                        
                        // Enable the auto-update checkbox
                        $autoUpdateCheckbox.prop('disabled', false);
                    }
                }
            });
        }
        
        // Fetch repository info from GitHub (includes refs now)
        function fetchRepoInfo(owner, repo) {
            $repoInfo.show();
            $repoInfoContent.hide();
            $repoInfoLoading.show();
            $refInput.prop('disabled', true).siblings('.spinner').addClass('is-active'); // Disable select and show spinner
            
            // AJAX request to get repository information (including refs)
            $.ajax({
                url: githubDeployer.ajaxUrl, // Use localized variable
                type: 'GET',
                data: {
                    action: 'github_deployer_repo_info', // Use the action that now includes refs
                    nonce: githubDeployer.nonce, // Use localized variable
                    owner: owner,
                    repo: repo
                },
                success: function(response) {
                    $refInput.prop('disabled', false).siblings('.spinner').removeClass('is-active'); // Re-enable select and hide spinner
                    if (response.success) {
                        displayRepoInfo(response.data); // Displays repo info
                        populateRefsDropdown(response.data); // Populates the dropdown
                    } else {
                        displayError(response.data.message || githubDeployer.strings.error); // Use localized variable
                        // Clear dropdown on error
                         $refInput.html('<option value="">' + githubDeployer.strings.error_fetching_refs + '</option>');
                    }
                },
                error: function() {
                    $refInput.prop('disabled', false).siblings('.spinner').removeClass('is-active'); // Re-enable select and hide spinner
                    displayError(githubDeployer.strings.error); // Use localized variable
                     // Clear dropdown on error
                    $refInput.html('<option value="">' + githubDeployer.strings.error_fetching_refs + '</option>');
                }
            });
        }
        
        // Populate the Branches/Tags/Releases dropdown
        function populateRefsDropdown(data) {
            let optionsHtml = '';
            let defaultBranch = data.default_branch || 'main';

            // Add default branch first
            optionsHtml += `<option value="${defaultBranch}" selected>${defaultBranch} (Default Branch)</option>`;

            // Add Releases (usually tags like v1.0.0)
            if (data.releases && data.releases.length) {
                optionsHtml += '<optgroup label="Releases">';
                data.releases.forEach(function(release) {
                    // Use tag_name for releases as the deployable ref
                    if (release.tag_name) { 
                       optionsHtml += `<option value="${release.tag_name}">${release.name || release.tag_name}</option>`;
                    }
                });
                optionsHtml += '</optgroup>';
            }
            
            // Add Branches
            if (data.branches && data.branches.length) {
                optionsHtml += '<optgroup label="Branches">';
                data.branches.forEach(function(branch) {
                    // Avoid adding the default branch again if it's in the list
                    if (branch.name !== defaultBranch) {
                         optionsHtml += `<option value="${branch.name}">${branch.name}</option>`;
                    }
                });
                optionsHtml += '</optgroup>';
            }

            // Add Tags (excluding tags that are already listed as releases)
            if (data.tags && data.tags.length) {
                optionsHtml += '<optgroup label="Tags">';
                const releaseTags = (data.releases || []).map(r => r.tag_name);
                data.tags.forEach(function(tag) {
                    if (!releaseTags.includes(tag.name)) {
                         optionsHtml += `<option value="${tag.name}">${tag.name}</option>`;
                    }
                });
                optionsHtml += '</optgroup>';
            }

            $refInput.html(optionsHtml); // Update the select dropdown
        }

        // Display repository information (Removed the old branch/tag list display)
        function displayRepoInfo(data) {
            let html = '<div class="github-deployer-repo-details">';
            
            // Repository info
            html += '<div class="repo-info">';
            html += '<h3>' + (data.full_name || (data.owner + '/' + data.name)) + '</h3>'; // Ensure full_name exists
            if (data.description) {
                html += '<p>' + data.description + '</p>';
            }
            html += '</div>';
            
            // Repository stats
            html += '<div class="repo-stats">';
            html += '<div class="stat"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M8 .25a.75.75 0 01.673.418l1.882 3.815 4.21.612a.75.75 0 01.416 1.279l-3.046 2.97.719 4.192a.75.75 0 01-1.088.791L8 12.347l-3.766 1.98a.75.75 0 01-1.088-.79l.72-4.194L.818 6.374a.75.75 0 01.416-1.28l4.21-.611L7.327.668A.75.75 0 018 .25z"></path></svg>' + (data.stargazers_count !== undefined ? data.stargazers_count : 'N/A') + '</div>';
            html += '<div class="stat"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M5 3.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm0 2.122a2.25 2.25 0 10-1.5 0v.878A2.25 2.25 0 005.75 8.5h1.5v2.128a2.251 2.251 0 101.5 0V8.5h1.5a2.25 2.25 0 002.25-2.25v-.878a2.25 2.25 0 10-1.5 0v.878a.75.75 0 01-.75.75h-4.5A.75.75 0 015 6.25v-.878zm3.75 7.378a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm3-8.75a.75.75 0 100-1.5.75.75 0 000 1.5z"></path></svg>' + (data.forks_count !== undefined ? data.forks_count : 'N/A') + '</div>';
            html += '<div class="stat"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M7.467.133a1.75 1.75 0 011.066 0l5.25 1.68A1.75 1.75 0 0115 3.48V7c0 1.566-.32 3.182-1.303 4.682-.983 1.498-2.585 2.813-5.032 3.855a1.7 1.7 0 01-1.33 0c-2.447-1.042-4.049-2.357-5.032-3.855C1.32 10.182 1 8.566 1 7V3.48a1.75 1.75 0 011.217-1.667l5.25-1.68zm.61 1.429a.25.25 0 00-.153 0l-5.25 1.68a.25.25 0 00-.174.238V7c0 1.358.275 2.666 1.057 3.86.784 1.194 2.121 2.34 4.366 3.297a.2.2 0 00.154 0c2.245-.956 3.582-2.104 4.366-3.298C13.225 9.666 13.5 8.36 13.5 7V3.48a.25.25 0 00-.174-.237l-5.25-1.68zM9 10.5a1 1 0 11-2 0 1 1 0 012 0zm-.25-5.75a.75.75 0 10-1.5 0v3a.75.75 0 001.5 0v-3z"></path></svg>' + (data.license?.name || githubDeployer.strings.no_license) + '</div>'; // Use localized variable
            html += '</div>';
            html += '</div>'; // End github-deployer-repo-details

            // No longer display separate branch/tag lists here
            
            $repoInfoContent.html(html);
            $repoInfoLoading.hide();
            $repoInfoContent.show();
            
            // No longer need click handlers for li items here
            
            // Check if this plugin/theme already exists
            checkForExisting();
        }
        
        // Display error message
        function displayError(message) {
            $repoInfoContent.html('<div class="notice notice-error inline"><p>' + message + '</p></div>');
            $repoInfoLoading.hide();
            $repoInfoContent.show();
        }

        // Auto-update checkbox handling
        $('.auto-update-toggle').on('change', function() {
            var checkbox = $(this);
            var repoId = checkbox.data('repo-id');
            var enabled = checkbox.is(':checked');
            var nonce = checkbox.data('nonce');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'github_deployer_toggle_auto_update',
                    repo_id: repoId,
                    enabled: enabled ? 1 : 0,
                    _ajax_nonce: nonce
                },
                beforeSend: function() {
                    checkbox.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        checkbox.prop('disabled', false);
                        
                        // Show success notice
                        var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        var noticesContainer = $('.github-deployer-notices');
                        
                        if (noticesContainer.length) {
                            noticesContainer.append(notice);
                        } else {
                            $('.wrap > h1').after(notice);
                        }
                        
                        // Auto dismiss after 3 seconds
                        setTimeout(function() {
                            notice.fadeOut(400, function() {
                                notice.remove();
                            });
                        }, 3000);
                    } else {
                        // Re-enable checkbox
                        checkbox.prop('disabled', false);
                        
                        // Revert checkbox state
                        checkbox.prop('checked', !enabled);
                        
                        // Show error
                        alert(response.data.message || 'Error updating auto-update status.');
                    }
                },
                error: function() {
                    // Re-enable checkbox
                    checkbox.prop('disabled', false);
                    
                    // Revert checkbox state
                    checkbox.prop('checked', !enabled);
                    
                    // Show error
                    alert('Error connecting to the server. Please try again.');
                }
            });
        });

        // Repository tab functionality
        if ($('.github-deployer-browse-section').length) {
            var repoList = $('#github-deployer-repo-list');
            var searchForm = $('.github-deployer-search-form');
            var searchTypeSelect = $('#github-deployer-repo-type');
            var searchQueryInput = $('#github-deployer-repo-query');
            var searchButton = $('#github-deployer-fetch-repos');
            var currentPage = 1;
            var reposPerPage = 10;
            var loadingTemplate = '<div class="loading">Loading repositories...</div>';
            var noResultsTemplate = '<div class="no-results">No repositories found.</div>';
            var errorTemplate = '<div class="error-message">Error loading repositories. Please try again.</div>';

            // Show/hide search query field based on selected type
            searchTypeSelect.on('change', function() {
                var selectedType = $(this).val();
                var queryLabel = $('#github-deployer-query-label');
                
                if (selectedType === 'search') {
                    queryLabel.text('Search Query');
                    searchQueryInput.attr('placeholder', 'Enter search query...').closest('.search-form-query').show();
                } else if (selectedType === 'public') {
                    queryLabel.text('GitHub Username');
                    searchQueryInput.attr('placeholder', 'Enter GitHub username...').closest('.search-form-query').show();
                } else if (selectedType === 'org') {
                    queryLabel.text('Organization Name');
                    searchQueryInput.attr('placeholder', 'Enter organization name...').closest('.search-form-query').show();
                } else {
                    // User's own repositories (no query needed)
                    searchQueryInput.closest('.search-form-query').hide();
                }
            });

            // Trigger change event to set initial state
            searchTypeSelect.trigger('change');

            // Handle search button click
            searchButton.on('click', function(e) {
                e.preventDefault();
                fetchRepositories(1);
            });

            // Function to fetch repositories from GitHub
            function fetchRepositories(page) {
                var searchType = searchTypeSelect.val();
                var searchQuery = searchQueryInput.val();
                
                // Show loading indicator
                repoList.html(loadingTemplate).show();
                
                // Reset current page if new search
                if (page === 1) {
                    currentPage = 1;
                }
                
                // Make AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'GET',
                    data: {
                        action: 'github_deployer_fetch_repositories',
                        nonce: github_deployer.nonce,
                        type: searchType,
                        query: searchQuery,
                        page: page
                    },
                    success: function(response) {
                        if (response.success) {
                            displayRepositories(response.data);
                        } else {
                            repoList.html(errorTemplate);
                            console.error('Error:', response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        repoList.html(errorTemplate);
                        console.error('AJAX Error:', status, error);
                    }
                });
            }

            // Function to display repositories
            function displayRepositories(data) {
                if (!data.repositories || data.repositories.length === 0) {
                    repoList.html(noResultsTemplate);
                    return;
                }
                
                var html = '<div class="repos-container">';
                var repos = data.repositories;
                var totalPages = Math.ceil(data.total_count / reposPerPage);
                
                // Build repository items
                for (var i = 0; i < repos.length; i++) {
                    html += buildRepoItem(repos[i]);
                }
                
                html += '</div>';
                
                // Add pagination if needed
                if (totalPages > 1) {
                    html += buildPagination(data.page, totalPages);
                }
                
                // Update the repository list
                repoList.html(html);
                
                // Attach event handlers to newly created elements
                attachRepoEventHandlers();
            }

            // Function to build a repository item HTML
            function buildRepoItem(repo) {
                var updated = repo.updated_at ? formatDate(repo.updated_at) : '';
                
                return '<div class="github-deployer-repo-item" data-repo-id="' + repo.id + '">' +
                    '<div class="repo-info">' +
                        '<h3><a href="' + repo.html_url + '" target="_blank">' + repo.full_name + '</a></h3>' +
                        (repo.description ? '<div class="description">' + repo.description + '</div>' : '') +
                        '<div class="meta">' +
                            '<span class="stars"><span class="dashicons dashicons-star-filled"></span> ' + repo.stargazers_count + '</span>' +
                            '<span class="forks"><span class="dashicons dashicons-networking"></span> ' + repo.forks_count + '</span>' +
                            (updated ? '<span class="updated"><span class="dashicons dashicons-calendar-alt"></span> ' + updated + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="repo-actions">' +
                        '<a href="' + buildDeployUrl(repo) + '" class="button button-primary">Deploy</a>' +
                        '<button class="button view-details" data-repo="' + repo.full_name + '">View Details</button>' +
                    '</div>' +
                '</div>';
            }

            // Helper function to build deploy URL
            function buildDeployUrl(repo) {
                var parts = repo.full_name.split('/');
                if (parts.length !== 2) {
                    return '#';
                }
                
                return window.location.href.replace(/tab=repositories/, 'tab=deploy') +
                       '&owner=' + encodeURIComponent(parts[0]) +
                       '&repo=' + encodeURIComponent(parts[1]) +
                       '&branch=' + encodeURIComponent(repo.default_branch);
            }

            // Function to build pagination HTML
            function buildPagination(currentPage, totalPages) {
                currentPage = parseInt(currentPage);
                var paginationHtml = '<div class="pagination">';
                
                // Previous button
                if (currentPage > 1) {
                    paginationHtml += '<a href="#" class="prev-page" data-page="' + (currentPage - 1) + '">&laquo; Previous</a>';
                }
                
                // Page numbers
                var startPage = Math.max(1, currentPage - 2);
                var endPage = Math.min(totalPages, startPage + 4);
                
                if (endPage - startPage < 4 && startPage > 1) {
                    startPage = Math.max(1, endPage - 4);
                }
                
                for (var i = startPage; i <= endPage; i++) {
                    var activeClass = i === currentPage ? ' active' : '';
                    paginationHtml += '<a href="#" class="page-num' + activeClass + '" data-page="' + i + '">' + i + '</a>';
                }
                
                // Next button
                if (currentPage < totalPages) {
                    paginationHtml += '<a href="#" class="next-page" data-page="' + (currentPage + 1) + '">Next &raquo;</a>';
                }
                
                paginationHtml += '</div>';
                return paginationHtml;
            }

            // Function to attach event handlers to repo items
            function attachRepoEventHandlers() {
                // Pagination click handlers
                $('.pagination a').on('click', function(e) {
                    e.preventDefault();
                    var page = $(this).data('page');
                    fetchRepositories(page);
                });
                
                // View details button click handler
                $('.view-details').on('click', function() {
                    var btn = $(this);
                    var repoFullName = btn.data('repo');
                    var repoItem = btn.closest('.github-deployer-repo-item');
                    var parts = repoFullName.split('/');
                    
                    if (parts.length !== 2) {
                        return;
                    }
                    
                    var owner = parts[0];
                    var repo = parts[1];
                    
                    // Check if details panel already exists and remove it
                    var existingPanel = repoItem.next('.github-deployer-repo-details-panel');
                    if (existingPanel.length) {
                        existingPanel.remove();
                        return; // Toggle effect - clicking again removes the panel
                    }
                    
                    // Create new details panel
                    var detailsPanel = $('<div class="github-deployer-repo-details-panel"><div class="loading">Loading repository details...</div></div>');
                    repoItem.after(detailsPanel);
                    
                    // Fetch repo details
                    $.ajax({
                        url: ajaxurl,
                        type: 'GET',
                        data: {
                            action: 'github_deployer_repo_info',
                            nonce: github_deployer.nonce,
                            owner: owner,
                            repo: repo
                        },
                        success: function(response) {
                            if (response.success) {
                                displayRepositoryDetails(detailsPanel, response.data);
                            } else {
                                detailsPanel.html('<div class="error-message">Error loading repository details: ' + 
                                    (response.data.message || 'Unknown error') + '</div>');
                            }
                        },
                        error: function() {
                            detailsPanel.html('<div class="error-message">Error connecting to the server. Please try again.</div>');
                        }
                    });
                });
            }

            // Function to display repository details
            function displayRepositoryDetails(panel, data) {
                // Get branches and tags from the response data
                var repo = data;
                var branches = data.branches || [];
                var tags = data.tags || [];
                
                var html = '<div class="github-deployer-repo-details">' +
                    '<div class="repo-info">' +
                        '<h3>' + repo.full_name + '</h3>' +
                        (repo.description ? '<p>' + repo.description + '</p>' : '') +
                    '</div>' +
                    '<div class="repo-stats">' +
                        '<div class="stat"><span class="dashicons dashicons-star-filled"></span> ' + repo.stargazers_count + ' stars</div>' +
                        '<div class="stat"><span class="dashicons dashicons-networking"></span> ' + repo.forks_count + ' forks</div>' +
                        '<div class="stat"><span class="dashicons dashicons-visibility"></span> ' + repo.watchers_count + ' watchers</div>' +
                        (repo.license ? '<div class="stat"><span class="dashicons dashicons-shield"></span> ' + repo.license.name + '</div>' : '') +
                    '</div>' +
                '</div>';
                
                // Add branches section
                html += '<div class="github-deployer-branches">' +
                    '<h4>Branches</h4>' +
                    '<ul>';
                
                if (branches.length) {
                    for (var i = 0; i < branches.length; i++) {
                        var isDefault = branches[i].name === repo.default_branch ? ' (default)' : '';
                        html += '<li data-branch="' + branches[i].name + '">' + branches[i].name + isDefault + '</li>';
                    }
                } else {
                    html += '<li>No branches available</li>';
                }
                
                html += '</ul></div>';
                
                // Add tags section
                html += '<div class="github-deployer-tags">' +
                    '<h4>Tags</h4>' +
                    '<ul>';
                
                if (tags.length) {
                    for (var j = 0; j < tags.length; j++) {
                        html += '<li data-tag="' + tags[j].name + '">' + tags[j].name + '</li>';
                    }
                } else {
                    html += '<li>No tags available</li>';
                }
                
                html += '</ul></div>';
                
                // Add deploy button
                html += '<div class="deploy-actions">' +
                    '<button class="button button-primary deploy-details" data-repo="' + repo.full_name + '" data-default-branch="' + repo.default_branch + '">Deploy This Repository</button>' +
                '</div>';
                
                panel.html(html);
                
                // Attach event handlers
                panel.find('.deploy-details').on('click', function() {
                    var repoFullName = $(this).data('repo');
                    var defaultBranch = $(this).data('default-branch');
                    var parts = repoFullName.split('/');
                    
                    if (parts.length === 2) {
                        // Redirect to deploy tab with pre-filled values
                        var url = window.location.href.replace(/tab=repositories/, 'tab=deploy') +
                                  '&owner=' + encodeURIComponent(parts[0]) +
                                  '&repo=' + encodeURIComponent(parts[1]) +
                                  '&branch=' + encodeURIComponent(defaultBranch);
                        
                        window.location.href = url;
                    }
                });
                
                // Branch click handler
                panel.find('.github-deployer-branches li').on('click', function() {
                    if ($(this).data('branch')) {
                        var repoFullName = panel.find('.deploy-details').data('repo');
                        var branch = $(this).data('branch');
                        var parts = repoFullName.split('/');
                        
                        if (parts.length === 2) {
                            // Redirect to deploy tab with pre-filled values
                            var url = window.location.href.replace(/tab=repositories/, 'tab=deploy') +
                                    '&owner=' + encodeURIComponent(parts[0]) +
                                    '&repo=' + encodeURIComponent(parts[1]) +
                                    '&branch=' + encodeURIComponent(branch);
                            
                            window.location.href = url;
                        }
                    }
                });
                
                // Tag click handler
                panel.find('.github-deployer-tags li').on('click', function() {
                    if ($(this).data('tag')) {
                        var repoFullName = panel.find('.deploy-details').data('repo');
                        var tag = $(this).data('tag');
                        var parts = repoFullName.split('/');
                        
                        if (parts.length === 2) {
                            // Redirect to deploy tab with pre-filled values
                            var url = window.location.href.replace(/tab=repositories/, 'tab=deploy') +
                                    '&owner=' + encodeURIComponent(parts[0]) +
                                    '&repo=' + encodeURIComponent(parts[1]) +
                                    '&branch=' + encodeURIComponent(tag);
                            
                            window.location.href = url;
                        }
                    }
                });
            }

            // Helper function to format dates
            function formatDate(dateString) {
                var date = new Date(dateString);
                var now = new Date();
                var diff = now - date; // Difference in milliseconds
                
                // Less than a day
                if (diff < 86400000) {
                    var hours = Math.floor(diff / 3600000);
                    if (hours < 1) {
                        var minutes = Math.floor(diff / 60000);
                        return minutes + ' minute' + (minutes !== 1 ? 's' : '') + ' ago';
                    }
                    return hours + ' hour' + (hours !== 1 ? 's' : '') + ' ago';
                }
                
                // Less than a week
                if (diff < 604800000) {
                    var days = Math.floor(diff / 86400000);
                    return days + ' day' + (days !== 1 ? 's' : '') + ' ago';
                }
                
                // Format as date
                var month = date.toLocaleString('default', { month: 'short' });
                var day = date.getDate();
                var year = now.getFullYear() !== date.getFullYear() ? ', ' + date.getFullYear() : '';
                return month + ' ' + day + year;
            }
        }

        // Connect/Disconnect repository functionality
        $('.toggle-connection').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var repoId = button.data('repo-id');
            var action = button.data('action'); // 'connect' or 'disconnect'
            var nonce = button.data('nonce');
            
            // Confirm disconnection
            if (action === 'disconnect' && !confirm('Are you sure you want to disconnect this repository? This will stop auto-updates for this repository.')) {
                return;
            }
            
            // Disable button to prevent multiple clicks
            button.prop('disabled', true).addClass('updating');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'github_deployer_toggle_connection',
                    repo_id: repoId,
                    connection_action: action,
                    _ajax_nonce: nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Reload page to show updated status
                        window.location.reload();
                    } else {
                        // Re-enable button
                        button.prop('disabled', false).removeClass('updating');
                        
                        // Show error
                        alert(response.data.message || 'Error updating connection status.');
                    }
                },
                error: function() {
                    // Re-enable button
                    button.prop('disabled', false).removeClass('updating');
                    
                    // Show error
                    alert('Error connecting to the server. Please try again.');
                }
            });
        });

        // Check connection status functionality
        $('.check-connection').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            var repoId = button.data('repo-id');
            var nonce = button.data('nonce');
            
            // Disable button to prevent multiple clicks
            button.prop('disabled', true).addClass('updating');
            button.html('Checking...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'github_deployer_check_connection',
                    repo_id: repoId,
                    _ajax_nonce: nonce
                },
                success: function(response) {
                    // Re-enable button
                    button.prop('disabled', false).removeClass('updating');
                    button.html('Check Connection');
                    
                    if (response.success) {
                        // Create and show notice
                        var notice = $('<div class="notice ' + (response.data.connected ? 'notice-success' : 'notice-error') + ' is-dismissible"><p>' + response.data.message + '</p></div>');
                        var noticesContainer = $('.github-deployer-notices');
                        
                        if (noticesContainer.length) {
                            noticesContainer.append(notice);
                        } else {
                            $('.wrap > h1').after(notice);
                        }
                        
                        // Update status display if provided
                        if (response.data.status_html) {
                            var statusCell = button.closest('tr').find('.connection-status');
                            if (statusCell.length) {
                                statusCell.html(response.data.status_html);
                            }
                        }
                        
                        // Auto dismiss after 5 seconds
                        setTimeout(function() {
                            notice.fadeOut(400, function() {
                                notice.remove();
                            });
                        }, 5000);
                    } else {
                        // Show error
                        alert(response.data.message || 'Error checking connection status.');
                    }
                },
                error: function() {
                    // Re-enable button
                    button.prop('disabled', false).removeClass('updating');
                    button.html('Check Connection');
                    
                    // Show error
                    alert('Error connecting to the server. Please try again.');
                }
            });
        });

        // Card layout implementation
        function initCardLayout() {
            // Wrap deploy form in card layout
            $('.github-deployer-deploy form').each(function() {
                if (!$(this).parent().hasClass('github-deployer-card-body')) {
                    $(this).wrap('<div class="github-deployer-card"></div>');
                    
                    // Add header before form
                    $(this).parent().prepend(
                        '<div class="github-deployer-card-header">' +
                            '<h2>' + $('.github-deployer-deploy h2').first().text() + '</h2>' +
                        '</div>'
                    );
                    
                    // Remove the original heading since we added it to the card header
                    $('.github-deployer-deploy h2').first().remove();
                    
                    // Wrap the form in a body div
                    $(this).wrap('<div class="github-deployer-card-body github-deployer-form"></div>');
                    
                    // Move the submit button to a footer
                    const submitButton = $(this).find('.submit');
                    submitButton.wrap('<div class="github-deployer-card-footer"></div>');
                    $(this).append(submitButton.parent());
                }
            });
            
            // Convert buttons to custom styled buttons
            $('.github-deployer-wrap .button-primary').addClass('github-deployer-button github-deployer-button-primary');
            $('.github-deployer-wrap .button-secondary').addClass('github-deployer-button github-deployer-button-secondary');
            
            // Convert notices to custom styled notices
            $('.github-deployer-wrap .notice-success').addClass('github-deployer-notice github-deployer-notice-success');
            $('.github-deployer-wrap .notice-error').addClass('github-deployer-notice github-deployer-notice-error');
            $('.github-deployer-wrap .notice-warning').addClass('github-deployer-notice github-deployer-notice-warning');
            
            // Style status indicators
            $('.status.connected').addClass('github-deployer-status github-deployer-status-connected');
            $('.status.disconnected').addClass('github-deployer-status github-deployer-status-disconnected');
            $('.status.enabled').addClass('github-deployer-status github-deployer-status-enabled');
            $('.status.disabled').addClass('github-deployer-status github-deployer-status-disabled');
        }
        
        // Repository list improvements
        function initRepositoryGrid() {
            if ($('#github-deployer-repo-list').length) {
                // Convert repo list items to grid cards
                $('#github-deployer-repo-list .github-deployer-repo-item').each(function() {
                    // Restructure each item for grid layout
                    const $item = $(this);
                    const $info = $item.find('.repo-info');
                    const $actions = $item.find('.repo-actions');
                    
                    const $header = $('<div class="github-deployer-repo-header"></div>');
                    const $body = $('<div class="github-deployer-repo-body"></div>');
                    const $footer = $('<div class="github-deployer-repo-footer"></div>');
                    
                    // Move title to header
                    $header.append($info.find('h3'));
                    
                    // Move description and meta to body
                    $body.append($info.find('.description'));
                    $body.append($info.find('.meta').addClass('github-deployer-repo-meta'));
                    
                    // Move actions to footer
                    $footer.append($actions);
                    
                    // Clear existing content and add new structure
                    $item.empty();
                    $item.append($header, $body, $footer);
                });
                
                // Wrap all items in a grid container
                if (!$('#github-deployer-repo-list .github-deployer-repo-item').parent().hasClass('github-deployer-repo-grid')) {
                    $('#github-deployer-repo-list .content').addClass('github-deployer-repo-grid');
                }
            }
        }
        
        // Enhanced form validation and feedback
        function initFormValidation() {
            // Add field validation
            $('.github-deployer-form input[required]').on('blur', function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                    
                    // Add validation message if not already added
                    if (!$(this).next('.validation-message').length) {
                        $(this).after('<span class="validation-message" style="color: var(--gd-error); font-size: 12px; display: block; margin-top: 5px;">This field is required</span>');
                    }
                } else {
                    $(this).removeClass('error');
                    $(this).next('.validation-message').remove();
                }
            });
            
            // Prevent form submission if required fields are empty
            $('.github-deployer-form form').on('submit', function(e) {
                let hasErrors = false;
                
                $(this).find('input[required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('error');
                        
                        // Add validation message if not already added
                        if (!$(this).next('.validation-message').length) {
                            $(this).after('<span class="validation-message" style="color: var(--gd-error); font-size: 12px; display: block; margin-top: 5px;">This field is required</span>');
                        }
                        
                        hasErrors = true;
                    }
                });
                
                if (hasErrors) {
                    e.preventDefault();
                    
                    // Scroll to first error
                    $('html, body').animate({
                        scrollTop: $('.error').first().offset().top - 100
                    }, 500);
                }
            });
        }
        
        // Toggle sections and tabs
        function initToggleSections() {
            // Show/hide fields based on selections
            $('#github-deployer-repo-type').on('change', function() {
                const type = $(this).val();
                
                if (type === 'search' || type === 'public' || type === 'org') {
                    $('.search-form-query').slideDown(200);
                    
                    // Update label based on type
                    if (type === 'search') {
                        $('#github-deployer-query-label').text('Search Query');
                    } else if (type === 'public') {
                        $('#github-deployer-query-label').text('GitHub Username');
                    } else if (type === 'org') {
                        $('#github-deployer-query-label').text('Organization Name');
                    }
                } else {
                    $('.search-form-query').slideUp(200);
                }
            }).trigger('change');
        }
        
        // Loading state for buttons
        function initButtonStates() {
            $('.github-deployer-wrap form').on('submit', function() {
                const $button = $(this).find('input[type="submit"], button[type="submit"]');
                const originalText = $button.val() || $button.text();
                
                // Save original text and set loading state
                $button.data('original-text', originalText);
                
                if ($button.is('input')) {
                    $button.val('Processing...');
                } else {
                    $button.text('Processing...');
                }
                
                $button.css({
                    'opacity': '0.7',
                    'pointer-events': 'none'
                });
            });
        }
        
        // Tooltips for UI elements
        function initTooltips() {
            // Add tooltip HTML
            $('[data-tooltip]').each(function() {
                const tooltipText = $(this).attr('data-tooltip');
                
                $(this).css('position', 'relative');
                
                $(this).append(
                    '<span class="github-deployer-tooltip" style="' +
                    'position: absolute;' +
                    'bottom: 100%;' +
                    'left: 50%;' +
                    'transform: translateX(-50%);' +
                    'background: rgba(0,0,0,0.8);' +
                    'color: white;' +
                    'padding: 5px 10px;' +
                    'border-radius: 4px;' +
                    'font-size: 12px;' +
                    'white-space: nowrap;' +
                    'z-index: 10;' +
                    'opacity: 0;' +
                    'visibility: hidden;' +
                    'transition: opacity 0.2s, visibility 0.2s;' +
                    'pointer-events: none;' +
                    '">' + tooltipText + '</span>'
                );
            });
            
            // Show tooltip on hover
            $(document).on('mouseenter', '[data-tooltip]', function() {
                $(this).find('.github-deployer-tooltip').css({
                    'opacity': '1',
                    'visibility': 'visible'
                });
            });
            
            // Hide tooltip when not hovering
            $(document).on('mouseleave', '[data-tooltip]', function() {
                $(this).find('.github-deployer-tooltip').css({
                    'opacity': '0',
                    'visibility': 'hidden'
                });
            });
        }
        
        // Confirmation dialogs for destructive actions
        function initConfirmActions() {
            $('.disconnect-form button, .disable-tracking-form button').on('click', function(e) {
                const action = $(this).text().trim().toLowerCase();
                const target = $(this).closest('tr').find('td:first-child a').text().trim();
                
                if (!confirm('Are you sure you want to ' + action + ' ' + target + '? This action cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            });
        }
        
        // Collapsible sections for long forms
        function initCollapsibleSections() {
            // Add toggle functionality to sections with the collapsible class
            $('.github-deployer-collapsible-section').each(function() {
                const $section = $(this);
                const $heading = $section.find('h3').first();
                const $content = $section.find('.github-deployer-collapsible-content');
                
                // Add toggle arrow to heading
                $heading.append('<span class="toggle-arrow" style="float: right; transition: transform 0.3s;">▼</span>');
                
                // Set initial state (collapsed by default if has class collapsed)
                if ($section.hasClass('collapsed')) {
                    $content.hide();
                    $heading.find('.toggle-arrow').css('transform', 'rotate(-90deg)');
                }
                
                // Toggle on click
                $heading.on('click', function() {
                    $content.slideToggle(250);
                    $heading.find('.toggle-arrow').css('transform', 
                        $content.is(':visible') ? 'rotate(0)' : 'rotate(-90deg)'
                    );
                });
            });
        }
        
        // Copy to clipboard functionality
        function initCopyToClipboard() {
            $('.github-deployer-copy-btn').on('click', function() {
                const $el = $($(this).data('copy-target'));
                const text = $el.is('input') ? $el.val() : $el.text();
                
                // Create temporary element
                const $temp = $('<textarea>');
                $('body').append($temp);
                $temp.val(text).select();
                
                // Copy
                document.execCommand('copy');
                $temp.remove();
                
                // Show success message
                const $btn = $(this);
                const originalText = $btn.text();
                
                $btn.text('Copied!');
                setTimeout(function() {
                    $btn.text(originalText);
                }, 2000);
            });
        }
        
        // Initialize all UI enhancements
        function initUI() {
            initCardLayout();
            initRepositoryGrid();
            initFormValidation();
            initToggleSections();
            initButtonStates();
            initTooltips();
            initConfirmActions();
            initCollapsibleSections();
            initCopyToClipboard();
        }
        
        // Initialize the UI
        initUI();
        
        // Fetch repositories AJAX functionality
        $('#github-deployer-fetch-repos').on('click', function() {
            const repoType = $('#github-deployer-repo-type').val();
            let query = '';
            
            if (repoType === 'search' || repoType === 'public' || repoType === 'org') {
                query = $('#github-deployer-repo-query').val();
                
                if (!query) {
                    alert('Please enter a search query or username');
                    return;
                }
            }
            
            // Show loading state
            $('#github-deployer-repo-list').show();
            $('#github-deployer-repo-list .content').html('');
            $('#github-deployer-repo-list .loading').show();
            
            // Make AJAX request
            $.ajax({
                url: githubDeployer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'github_deployer_get_repos',
                    nonce: githubDeployer.nonce,
                    type: repoType,
                    query: query,
                    page: 1
                },
                success: function(response) {
                    if (response.success) {
                        // Render repositories
                        $('#github-deployer-repo-list .content').html(renderRepositories(response.data.repositories));
                        $('#github-deployer-repo-list .pagination').html(renderPagination(response.data.page, response.data.total_pages));
                        
                        // Initialize the UI for the new content
                        initRepositoryGrid();
                    } else {
                        // Show error
                        $('#github-deployer-repo-list .content').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    // Show error
                    $('#github-deployer-repo-list .content').html('<div class="notice notice-error"><p>Error connecting to server. Please try again.</p></div>');
                },
                complete: function() {
                    // Hide loading state
                    $('#github-deployer-repo-list .loading').hide();
                }
            });
        });
        
        // Helper function to render repository items
        function renderRepositories(repos) {
            if (!repos.length) {
                return '<div class="notice notice-warning"><p>No repositories found matching your criteria.</p></div>';
            }
            
            let html = '';
            
            repos.forEach(function(repo) {
                html += '<div class="github-deployer-repo-item">';
                html += '<div class="repo-info">';
                html += '<h3><a href="' + repo.html_url + '" target="_blank">' + repo.full_name + '</a></h3>';
                html += '<p class="description">' + (repo.description || 'No description available') + '</p>';
                html += '<p class="meta">';
                html += '<span class="stars"><span class="dashicons dashicons-star-filled"></span> ' + repo.stargazers_count + '</span>';
                html += '<span class="forks"><span class="dashicons dashicons-networking"></span> ' + repo.forks_count + '</span>';
                html += '<span class="updated"><span class="dashicons dashicons-calendar-alt"></span> ' + repo.updated_at.split('T')[0] + '</span>';
                html += '</p>';
                html += '</div>';
                
                html += '<div class="repo-actions">';
                html += '<a href="' + repo.deploy_url + '" class="button button-primary">Deploy</a>';
                html += '</div>';
                html += '</div>';
            });
            
            return html;
        }
        
        // Helper function to render pagination
        function renderPagination(currentPage, totalPages) {
            if (totalPages <= 1) {
                return '';
            }
            
            let html = '<div class="tablenav-pages">';
            html += '<span class="displaying-num">' + totalPages + ' pages</span>';
            html += '<span class="pagination-links">';
            
            // Previous page
            if (currentPage > 1) {
                html += '<a class="prev-page button" href="#" data-page="' + (currentPage - 1) + '">‹</a>';
            } else {
                html += '<span class="prev-page button disabled">‹</span>';
            }
            
            // Page numbers
            html += '<span class="paging-input">';
            html += '<input class="current-page" type="text" value="' + currentPage + '" size="1">';
            html += '<span class="tablenav-paging-text"> of <span class="total-pages">' + totalPages + '</span></span>';
            html += '</span>';
            
            // Next page
            if (currentPage < totalPages) {
                html += '<a class="next-page button" href="#" data-page="' + (currentPage + 1) + '">›</a>';
            } else {
                html += '<span class="next-page button disabled">›</span>';
            }
            
            html += '</span>';
            html += '</div>';
            
            return html;
        }
        
        // Handle pagination clicks
        $(document).on('click', '.pagination-links a.button', function(e) {
            e.preventDefault();
            
            const page = $(this).data('page');
            const repoType = $('#github-deployer-repo-type').val();
            const query = $('#github-deployer-repo-query').val();
            
            // Show loading state
            $('#github-deployer-repo-list .content').html('');
            $('#github-deployer-repo-list .loading').show();
            
            // Make AJAX request
            $.ajax({
                url: githubDeployer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'github_deployer_get_repos',
                    nonce: githubDeployer.nonce,
                    type: repoType,
                    query: query,
                    page: page
                },
                success: function(response) {
                    if (response.success) {
                        // Render repositories
                        $('#github-deployer-repo-list .content').html(renderRepositories(response.data.repositories));
                        $('#github-deployer-repo-list .pagination').html(renderPagination(response.data.page, response.data.total_pages));
                        
                        // Initialize the UI for the new content
                        initRepositoryGrid();
                    } else {
                        // Show error
                        $('#github-deployer-repo-list .content').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    // Show error
                    $('#github-deployer-repo-list .content').html('<div class="notice notice-error"><p>Error connecting to server. Please try again.</p></div>');
                },
                complete: function() {
                    // Hide loading state
                    $('#github-deployer-repo-list .loading').hide();
                    
                    // Scroll to top of repository list
                    $('html, body').animate({
                        scrollTop: $('#github-deployer-repo-list').offset().top - 50
                    }, 500);
                }
            });
        });
    });
})(jQuery);