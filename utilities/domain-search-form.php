<?php
// Domain Search Form - Reusable domain search widget
// Usage: Set variables before include: $search_placeholder, $form_action, $popular_extensions
?>
<div style="background: rgba(0,0,0,0.8); padding: 2.5rem; border-radius: 16px; border: 1px solid rgba(0,255,255,0.3); margin: 2rem 0;">
    <h3 style="color: var(--cyber-neon-cyan); text-align: center; margin-bottom: 1.5rem; font-family: var(--font-heading);">
        SEARCH NEURAL DOMAINS
    </h3>
    
    <form id="domain-search-form" <?php if(isset($form_action)): ?>action="<?php echo $form_action; ?>"<?php endif; ?> method="GET">
        <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input 
                    type="text" 
                    name="domain" 
                    placeholder="<?php echo isset($search_placeholder) ? $search_placeholder : 'Enter your domain name...'; ?>"
                    style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; font-size: 1.1rem;"
                    required
                >
            </div>
            
            <div style="min-width: 120px;">
                <select name="extension" style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;">
                    <option value=".com">.com</option>
                    <option value=".co.za">.co.za</option>
                    <option value=".net">.net</option>
                    <option value=".org">.org</option>
                    <option value=".io">.io</option>
                    <option value=".tech">.tech</option>
                    <option value=".online">.online</option>
                    <option value=".store">.store</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="padding: 16px 24px; font-size: 1.1rem; white-space: nowrap;">
                <i class="fas fa-search" style="margin-right: 0.5rem;"></i>
                SCAN DOMAIN
            </button>
        </div>
    </form>
    
    <?php if(isset($popular_extensions) && !empty($popular_extensions)): ?>
        <div style="text-align: center;">
            <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem; font-size: 0.9rem;">Popular Extensions:</p>
            <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                <?php foreach($popular_extensions as $ext): ?>
                    <span style="background: rgba(0,255,255,0.1); color: var(--cyber-neon-cyan); padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem; border: 1px solid rgba(0,255,255,0.3);">
                        <?php echo $ext; ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div id="domain-search-results" style="margin-top: 2rem; display: none;">
        <!-- Domain search results will be populated here via JavaScript -->
    </div>
</div>