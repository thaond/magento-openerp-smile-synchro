# -*- encoding: utf-8 -*-
##############################################################################
#
# Copyright (c) 2008 Smile S.A. (http://www.smile.fr) All Rights Reserved.
# @authors: Sylvain Pamart, Rapha�l Valyi
#
# WARNING: This program as such is intended to be used by professional
# programmers who take the whole responsability of assessing all potential
# consequences resulting from its eventual inadequacies and bugs
# End users who are looking for a ready-to-use solution with commercial
# garantees and support are strongly adviced to contract a Free Software
# Service Company
#
# This program is Free Software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
#
##############################################################################

import wizard
import pooler
import xmlrpclib
import netsvc
from xml.parsers.expat import ExpatError


#===============================================================================
#    Information Form & Fields
#===============================================================================

_export_done_form = '''<?xml version="1.0"?>
<form string="Product and Stock Synchronization">
    <separator string="Products exported" colspan="4" />
    <field name="prod_new"/>
    <field name="prod_update"/>
</form>'''

_export_done_fields = {
    'prod_new': {'string':'New products', 'readonly': True, 'type':'integer'},
    'prod_update': {'string':'Updated products', 'readonly': True, 'type':'integer'},
}


def _do_export(self, cr, uid, data, context):
    
    #===============================================================================
    #  Init
    #===============================================================================

    prod_new = 0
    prod_update = 0
    logger = netsvc.Logger()
    pool = pooler.get_pool(cr.dbname)
    prod_ids = pool.get('product.product').search(cr, uid, [('exportable','=',True)]) #NB uid is the user id to enforce the rights accesses
    
    # Server communication
    magento_web_id=pool.get('magento.web').search(cr,uid,[('magento_id','=',1)])
    try:
        magento_web=pool.get('magento.web').browse(cr,uid,magento_web_id[0])
        server = xmlrpclib.ServerProxy("%sapp/code/community/Smile_OpenERP_Synchro/openerp-synchro.php" % magento_web.magento_url)# % website.url)
    except:
        raise wizard.except_wizard("UserError", "You must have a declared website with a valid URL! provided URL: %s/openerp-synchro.php" % magento_web.magento_url)
    
    #===============================================================================
    #  Product packaging
    #===============================================================================
    for product in pool.get('product.product').browse(cr, uid, prod_ids, context=context):
    
        category_tab ={'0':1}
        key=1
        tax_class_id = 1
        last_category = product.categ_id
        while(type(last_category.parent_id.id) == (int)):
            category_tab[str(key)]=last_category.magento_id
            last_category=pool.get('product.category').browse(cr, uid, last_category.parent_id.id)
            key=key+1

            
        if(product.magento_tax_class_id != 0):
            tax_class_id=product.magento_tax_class_id
            
         
        webproduct={
            'magento_id': product.magento_id or int(0),
            'magento_product_type': product.categ_id.magento_product_type or 0,
            'magento_product_attribute_set_id': product.categ_id.magento_product_attribute_set_id or 0,
            'quantity': product.virtual_available or int(0),
               
            'product_data': {
                'sku': 'mag'+str(product.id) or int(0),
                'name': product.name or '',
                'price' : product.list_price or float(0.0), 
                'weight': product.weight_net or float(0.0), 
                'category_ids': category_tab, #fix product.categ_id.magento_id or int(0), 
                'description' : product.description or 'Auto description',
                'short_description' : product.description_sale or 'Auto short description',
                'tax_class_id': tax_class_id or 0,
                 }
        }
        
        
        #===============================================================================
        #  Product upload to Magento
        #===============================================================================
        
        try:
            updated_magento_id = server.product_sync([webproduct])   
            
            # update Magento id in OpenERP or log error
            if updated_magento_id != 0 :
                if int(product.magento_id) == int(updated_magento_id):
                    prod_update += 1
                else:
                    prod_new += 1
                pool.get('product.product').write(cr, uid, product.id, {'magento_id': updated_magento_id})
            else:
                logger.notifyChannel("Magento Export", netsvc.LOG_ERROR, "Magento couldn't create or update the product ID %s , see your debug.xmlrpc.log in the Smile_OpenERP_Synch folder in your Apache!" % product.id)  
        except ExpatError, error:
            logger.notifyChannel("Magento Export", netsvc.LOG_ERROR, "Product ID %s has error ! See your debug.xmlrpc.log in the Smile_OpenERP_Synch folder in your Apache! \nError %s" %(product.id, error))
        

    return {'prod_new':prod_new, 'prod_update':prod_update}



#===============================================================================
#   Wizard Declaration
#===============================================================================

class wiz_magento_product_synchronize(wizard.interface):
    states = {
        'init': {
            'actions': [_do_export],
            'result': {'type': 'form', 'arch': _export_done_form, 'fields': _export_done_fields, 'state': [('end', 'End')] }
        }
    }
wiz_magento_product_synchronize('magento.products.sync');
