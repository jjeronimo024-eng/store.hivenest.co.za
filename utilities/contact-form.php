<?php
// Contact Form - Reusable contact form component  
// Usage: Set variables before include: $form_title, $form_subtitle, $form_id, $form_action
?>
<div>
    <h2><?php echo isset($form_title) ? $form_title : 'SEND NEURAL TRANSMISSION'; ?></h2>
    <p class="hero-subtitle" style="margin-bottom: 2rem;">
        <?php echo isset($form_subtitle) ? $form_subtitle : 'Send us a message and we\'ll respond within the neural network timeframe.'; ?>
    </p>
    
    <form id="<?php echo isset($form_id) ? $form_id : 'contact-form'; ?>" 
          class="contact-form"
          <?php if(isset($form_action)): ?>action="<?php echo $form_action; ?>"<?php endif; ?>
          style="background: rgba(0,0,0,0.6); padding: 2rem; border-radius: 16px; border: 1px solid rgba(0,255,255,0.3);">
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                Neural Identity
            </label>
            <input 
                type="text" 
                name="name" 
                placeholder="Your name" 
                style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;"
                required
            >
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                Digital Address
            </label>
            <input 
                type="email" 
                name="email" 
                placeholder="your@email.com" 
                style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;"
                required
            >
        </div>
        
        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                Subject Protocol
            </label>
            <select name="subject" style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white;">
                <option value="general">General Inquiry</option>
                <option value="sales">Sales Request</option>
                <option value="support">Technical Support</option>
                <option value="billing">Billing Question</option>
                <option value="partnership">Partnership Proposal</option>
            </select>
        </div>
        
        <div style="margin-bottom: 2rem;">
            <label style="display: block; color: var(--cyber-neon-cyan); margin-bottom: 0.5rem; font-weight: 600;">
                Neural Message
            </label>
            <textarea 
                name="message" 
                rows="6" 
                placeholder="Your message..." 
                style="width: 100%; padding: 16px; border-radius: 8px; border: 1px solid rgba(0,255,255,0.3); background: rgba(0,0,0,0.5); color: white; resize: vertical;"
                required
            ></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 16px; font-size: 1.1rem;">
            <i class="fas fa-rocket" style="margin-right: 0.5rem;"></i>
            TRANSMIT MESSAGE
        </button>
        
        <div id="form-result" style="margin-top: 1rem; padding: 1rem; border-radius: 8px; display: none;"></div>
    </form>
</div>