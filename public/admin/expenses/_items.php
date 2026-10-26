<?php
// Partial: the list of things in an expense request ($open from the including page). Never opened directly.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit('Not found'); }
?>
<div class="table-responsive"><table class="table table-sm mb-2">
    <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price each</th><th class="text-end">Total</th></tr></thead>
    <tbody><?php foreach ($open['items'] as $it): ?>
        <tr><td><?php echo $e($it['item']); ?></td><td class="text-end"><?php echo (int) $it['qty']; ?></td>
            <td class="text-end"><?php echo $e(\Models\ExpenseModel::money((float) $it['price'])); ?></td><td class="text-end"><?php echo $e(\Models\ExpenseModel::money((float) $it['total'])); ?></td></tr>
    <?php endforeach; ?></tbody>
    <tfoot><tr><th colspan="3">Estimated total</th><th class="text-end"><?php echo $e(\Models\ExpenseModel::money((float) $open['estimated_total'])); ?></th></tr></tfoot>
</table></div>
<div class="small"><strong>Where:</strong> <?php echo $e($open['where_buy']); ?> · <strong>When:</strong> <?php echo $e(date('D j M Y', strtotime($open['needed_on']))); ?></div>
<?php if ($open['note']): ?><div class="small"><strong>Note:</strong> <?php echo $e($open['note']); ?></div><?php endif; ?>
