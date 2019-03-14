<?php

namespace tests\units;

use atoum;

class PluginActualtimeConfig extends atoum {

   public function testRightname() {
      $this
         ->given($conf = $this->getTestedClassName())
            ->string($conf::$rightname)
               ->isEqualTo('config');
   }

   public function testGetTypeName() {
      $this
         ->if($class = $this->testedClass->getClass())
         ->then
            ->string($class::getTypeName())
               ->isNotEmpty();
   }

   public function testGetConfig() {
      $this
         ->given($this->newTestedInstance)
            ->object($this->testedInstance->getConfig())
               ->isInstanceOfTestedClass();
   }

   public function testIsEnabled() {
      $this
         ->given($this->newTestedInstance)
            ->boolean($this->testedInstance->isEnabled())
               ->isTrue();
   }

   public function testShowTimerPopup() {
      $this
         ->given($this->newTestedInstance)
            ->boolean($this->testedInstance->showTimerPopup())
               ->isTrue();
   }

   public function testCanView() {
      $this
         ->given($this->newTestedInstance)
            ->boolean($this->testedInstance->canView())
               ->isFalse();
   }

   public function testCanCreate() {
      $this
         ->given($this->newTestedInstance)
            ->boolean($this->testedInstance->canCreate())
               ->isFalse();
   }

}
