<script type="text/javascript">
  function selectFile() {
    var fileName = document.getElementById("file-name").files[0].name;
    document.getElementById("input-name").value = fileName;
  }
</script>

<?php echo $this->Form->create('Attachment', array('type' => 'file')); ?>
<?php echo $this->Form->input('Attachment.attachment', array('type' => 'file','id' => 'file-name','onchange'=>'selectFile()')); ?>
<?php echo $this->Form->input('Attachment.name',array('id' => 'input-name')); ?>
<?php echo $this->Form->input('Attachment.dir', array('type' => 'hidden')); ?>
<?php echo $this->Form->input('Attachment.wiki_page_id', array('type' => 'hidden')); ?>
<?php echo $this->Form->end(__('Submit')); ?>