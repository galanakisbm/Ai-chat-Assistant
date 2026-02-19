# 🎉 MEGA UPDATE - Testing & Deployment Guide

## Quick Start

### Prerequisites
- PrestaShop 8.0.0 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Valid OpenAI API Key

### Installation Steps

1. **Backup your site**
   ```bash
   # Backup database
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
   
   # Backup module files
   tar -czf optic_aichat_backup_$(date +%Y%m%d).tar.gz optic_aichat/
   ```

2. **Upload updated files**
   - Replace `/modules/optic_aichat/optic_aichat.php`
   - Replace `/modules/optic_aichat/controllers/front/ajax.php`
   - Add `/modules/optic_aichat/.gitignore` (optional)
   - Add `/modules/optic_aichat/MEGA_UPDATE_GUIDE.md` (optional)

3. **Run database migration** (if upgrading existing installation)
   ```sql
   -- Add analytics table if not exists
   CREATE TABLE IF NOT EXISTS `ps_optic_aichat_analytics` (
       `id_conversation` INT AUTO_INCREMENT PRIMARY KEY,
       `id_customer` INT DEFAULT NULL,
       `user_message` TEXT,
       `bot_response` MEDIUMTEXT,
       `products_mentioned` VARCHAR(255) DEFAULT NULL,
       `response_time` FLOAT DEFAULT 0,
       `detected_language` VARCHAR(5) DEFAULT NULL,
       `date_add` DATETIME,
       INDEX `date_add` (`date_add`),
       INDEX `id_customer` (`id_customer`)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
   
   -- Add new configuration keys
   INSERT IGNORE INTO `ps_configuration` (`name`, `value`) VALUES
   ('OPTIC_AICHAT_AUTO_LANGUAGE', '1'),
   ('OPTIC_AICHAT_FALLBACK_LANG', 'el');
   ```

4. **Clear PrestaShop cache**
   ```bash
   # From PrestaShop root directory
   rm -rf var/cache/*
   ```

5. **Test the module**
   - Navigate to Modules → OpticWeb AI Chat
   - Verify all 4 tabs are visible
   - Test each save button independently

---

## 🧪 Testing Checklist

### Tab 1: Basic Settings
- [ ] Enter API Key (< 20 chars) → Should show error
- [ ] Enter valid API Key (≥ 20 chars) → Should save successfully
- [ ] Change primary color → Should apply to chat widget
- [ ] Toggle auto language detection → Should save
- [ ] Select fallback language → Should save
- [ ] Click "Save Basic Settings" → Only basic settings should update

### Tab 2: XML Product Feed
- [ ] Upload XML file → Should show success with product count
- [ ] Upload invalid XML → Should show error
- [ ] View Preview panel → Should show first product data
- [ ] Map fields → Should auto-suggest correct mappings
- [ ] Click "Save Mapping & Index Products" → Should re-index
- [ ] Click "Delete XML & Clear Cache" → Should remove files and configs
- [ ] Verify cache deleted: `ls modules/optic_aichat/uploads/` should be empty

### Tab 3: Knowledge Base
- [ ] Enable "Include On-Sale Products" → Should save
- [ ] Enable "Include Active Coupons" → Should save
- [ ] Add Store Policies text → Should save
- [ ] Add FAQ text → Should save
- [ ] Click "Save Knowledge Base" → Only KB settings should update
- [ ] Ask AI "Έχετε εκπτώσεις?" → Should mention actual sales

### Tab 4: Analytics
- [ ] Send 5 test messages in chat
- [ ] Refresh Analytics tab
- [ ] Verify metrics display:
  - Total Conversations (should be > 0)
  - Total Messages (should be 5+)
  - Avg Response Time (should show seconds)
  - Avg Messages/Day (should show calculated value)
- [ ] Check Top Questions table → Should show your messages
- [ ] Check Popular Search Terms → Should show extracted keywords
- [ ] Click "Export to CSV" → Should download file
- [ ] Open CSV → Verify data is present
- [ ] Click "Clear Old Data" → Confirm dialog → Should succeed

### Chat Widget (Frontend)
- [ ] Send Greek message → AI responds in Greek
- [ ] Send English message → AI responds in English
- [ ] Ask about sales → AI mentions actual on-sale products
- [ ] Ask about policies → AI uses configured store policies
- [ ] Ask FAQ question → AI uses configured FAQ
- [ ] Check network tab → Verify no errors

### Database Verification
```sql
-- Verify analytics table exists
SHOW TABLES LIKE '%optic_aichat_analytics%';

-- Check analytics data
SELECT COUNT(*) FROM ps_optic_aichat_analytics;

-- View recent conversations
SELECT user_message, detected_language, DATE(date_add) 
FROM ps_optic_aichat_analytics 
ORDER BY date_add DESC 
LIMIT 10;

-- Verify configuration keys
SELECT name, value FROM ps_configuration 
WHERE name LIKE 'OPTIC_AICHAT_%' 
ORDER BY name;
```

---

## 🐛 Troubleshooting

### Issue: API Key not saving
**Solution:** Check file permissions on configuration table:
```sql
SELECT * FROM ps_configuration WHERE name = 'OPTIC_AICHAT_API_KEY';
```

### Issue: Analytics not logging
**Check:**
1. Table exists: `SHOW TABLES LIKE '%optic_aichat_analytics%';`
2. Permissions: User can INSERT into table
3. Error logs: Check PrestaShop error logs

### Issue: Language detection not working
**Check:**
1. Configuration saved: `SELECT value FROM ps_configuration WHERE name = 'OPTIC_AICHAT_AUTO_LANGUAGE';`
2. Send test with Greek characters: "Γεια σου"
3. Check analytics table for detected_language column

### Issue: Knowledge Base not affecting responses
**Check:**
1. XML file uploaded and indexed
2. Knowledge Base settings saved
3. Products cache exists: `ls modules/optic_aichat/uploads/products_cache.json`
4. Cache is valid JSON: `cat modules/optic_aichat/uploads/products_cache.json | python -m json.tool`

### Issue: Tabs not working
**Check:**
1. JavaScript errors in browser console
2. URL changes when clicking tabs (should have `&tab=xxx`)
3. Clear PrestaShop cache

---

## 📊 Performance Considerations

### Database Optimization
```sql
-- Add index on date_add for faster analytics queries
ALTER TABLE ps_optic_aichat_analytics 
ADD INDEX idx_date_customer (date_add, id_customer);

-- Clean old data regularly (run monthly)
DELETE FROM ps_optic_aichat_analytics 
WHERE date_add < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Cache Management
- Products cache file can grow large with many products
- Consider limiting to 1000 most popular products
- Refresh cache weekly for updated prices/stock

### API Rate Limits
- Monitor OpenAI API usage
- Consider implementing request throttling
- Cache common questions/answers

---

## 🔒 Security Best Practices

### API Key Protection
- Never commit API keys to git
- Store in environment variables when possible
- Rotate keys periodically

### Input Validation
- All user input is sanitized with pSQL()
- Product mentions limited to 10 items
- Response length capped at MEDIUMTEXT (16MB)

### Database Security
- Use prepared statements (handled by PrestaShop)
- Validate all numeric inputs
- Escape all text fields

---

## 📈 Monitoring

### Key Metrics to Track
1. **Response Time**: Should be < 3 seconds
2. **Error Rate**: Should be < 1%
3. **Conversation Length**: Avg 3-5 messages
4. **Language Detection Accuracy**: > 95%

### Logging
```php
// Enable debug logging in PrestaShop
define('_PS_MODE_DEV_', true);

// Check logs
tail -f var/logs/*.log
```

---

## 🚀 Next Steps

After successful deployment:

1. **Monitor for 24 hours**
   - Check error logs
   - Verify analytics are logging
   - Test all features

2. **Optimize AI Prompts**
   - Review top questions
   - Adjust system prompt based on usage
   - Add FAQ for common questions

3. **Gather Feedback**
   - Ask users about chat experience
   - Review conversation quality
   - Identify improvement areas

4. **Plan Future Enhancements**
   - Add more languages
   - Implement conversation sessions
   - Create automated reports

---

## 📞 Support

If you encounter issues:

1. Check this guide first
2. Review error logs
3. Verify database tables and data
4. Test with minimal configuration
5. Create GitHub issue with details

---

## ✅ Success Criteria

Deployment is successful when:
- ✅ All 4 tabs load without errors
- ✅ Each save button works independently
- ✅ Analytics are being logged
- ✅ Language detection works correctly
- ✅ Knowledge Base affects AI responses
- ✅ No PHP errors in logs
- ✅ Chat widget displays correctly
- ✅ CSV export works

---

**Version:** 2.0.0 (MEGA UPDATE)
**Last Updated:** 2026-02-19
**Compatibility:** PrestaShop 8.0+

---

## 📝 Change Log

### v2.0.0 - MEGA UPDATE (2026-02-19)
- ✨ Added 4-tab interface
- ✨ Implemented Analytics Dashboard
- ✨ Added Knowledge Base integration
- ✨ Multi-language auto-detection
- 🔧 Fixed API Key validation
- 🔧 Separate save buttons per section
- 🔧 Delete XML functionality
- 🔧 Improved product mentions extraction
- 📚 Comprehensive documentation
- ⚡ Performance optimizations

### v1.0.0 - Initial Release
- Basic chat functionality
- XML product feed integration
- Page context awareness
- Color customization
