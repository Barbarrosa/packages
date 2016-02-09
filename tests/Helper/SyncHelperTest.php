<?php
namespace Terramar\Packages\Tests\Helper;

use Terramar\Packages\Entity\Package;
use Terramar\Packages\Events;
use Terramar\Packages\Helper\SyncHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Terramar\Packages\Entity\Remote;
class SyncHelperTest extends \PHPUnit_Framework_TestCase
{
    /** @var SyncHelper */
    private $sut;
    
    /** @var EventDispatcherInterface|\PHPUnit_Framework_MockObject_MockObject */
    private $eventDispatcher;
    
    public function setUp()
    {
        $this->eventDispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->sut = new SyncHelper($this->eventDispatcher);
    }
    
    public function testRegisterAdaptersRegistersEachAdapterNameWithLastAdapter()
    {
        $this->assertEmpty($this->sut->getAdapters());
        
        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $adapter->expects($this->any())->method('getName')
            ->will($this->returnValue('foobaradapter'));
        
        $this->sut->registerAdapter($adapter);
        
        $actualAdapters = $this->sut->getAdapters();
        $this->assertCount(1, $actualAdapters);
        $this->assertArrayHasKey(0, $actualAdapters);
        $this->assertSame($adapter, $actualAdapters[0]);

        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapterDiffName */
        $adapterDiffName = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $adapterDiffName->expects($this->any())->method('getName')
            ->will($this->returnValue('differentNameAdapter'));
        
        $this->sut->registerAdapter($adapterDiffName);
        $actualAdapters = $this->sut->getAdapters();
        $this->assertCount(2, $actualAdapters);
        $this->assertArrayHasKey(0, $actualAdapters);
        $this->assertArrayHasKey(1, $actualAdapters);
        $this->assertSame($adapter, $actualAdapters[0]);
        $this->assertSame($adapterDiffName, $actualAdapters[1]);

        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapterSameName */
        $adapterSameName = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $adapterSameName->expects($this->any())->method('getName')
            ->will($this->returnValue('foobaradapter'));

        $this->sut->registerAdapter($adapterSameName);
        $actualAdapters = $this->sut->getAdapters();
        $this->assertCount(2, $actualAdapters);
        $this->assertArrayHasKey(0, $actualAdapters);
        $this->assertArrayHasKey(1, $actualAdapters);
        $this->assertSame($adapterSameName, $actualAdapters[0]);
        $this->assertSame($adapterDiffName, $actualAdapters[1]);
    }
    
    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No adapter registered supports the given configuration
     */
    public function testSynchronizePackagesThrowsForUnsupportedRemoteConfigurationWithNoAdapters()
    {
        $remote = new Remote();
        $this->sut->synchronizePackages($remote);
    }
    

    /**
     * @expectedException \RuntimeException
     * @expectedExceptionMessage No adapter registered supports the given configuration
     */
    public function testSynchronizePackagesThrowsForUnsupportedRemoteConfigurationWithSomeAdapters()
    {
        $remote = new Remote();
        
        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $adapter->expects($this->any())->method('getName')
            ->will($this->returnValue('foobaradapter'));
        $adapter->expects($this->any())->method('supports')
            ->with($remote)
            ->will($this->returnValue(false));
        
        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $adapter2 = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $adapter2->expects($this->any())->method('getName')
            ->will($this->returnValue('differentAdapter'));
        $adapter2->expects($this->any())->method('supports')
            ->with($remote)
            ->will($this->returnValue(false));
        
        $this->sut->registerAdapter($adapter);
        $this->sut->registerAdapter($adapter2);
        $this->sut->synchronizePackages($remote);
    }
    
    public function testSynchronizePackagesDispatchesCreateEventsAndReturnsPackages()
    {
        $remote = new Remote();
        
        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $goodAdapter = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $goodAdapter->expects($this->any())->method('getName')
            ->will($this->returnValue('foobaradapter'));
        $goodAdapter->expects($this->any())->method('supports')
            ->with($remote)
            ->will($this->returnValue(true));
        
        $goodAdapter->expects($this->any())->method('synchronizePackages')
            ->with($remote)
            ->will($this->returnValue(
                $packages = array(
                    new Package(),
                    new Package(),
                    new Package(),
                    new Package(),
                )
            ));
        /** @var \Terramar\Packages\Helper\SyncAdapterInterface|\PHPUnit_Framework_MockObject_MockObject $adapter */
        $goodAdapter2 = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $goodAdapter2->expects($this->any())->method('getName')
            ->will($this->returnValue('foobaradapter2'));
        $goodAdapter2->expects($this->any())->method('supports')
            ->with($remote)
            ->will($this->returnValue(true));
        
        $badAdapter = $this->getMock('\Terramar\Packages\Helper\SyncAdapterInterface');
        $badAdapter->expects($this->any())->method('getName')
            ->will($this->returnValue('foobadddadapter'));
        $badAdapter->expects($this->any())->method('supports')
            ->with($remote)
            ->will($this->returnValue(false));
        
        $this->sut->registerAdapter($badAdapter);
        $this->sut->registerAdapter($goodAdapter);
        $this->sut->registerAdapter($goodAdapter2);
        
        $this->eventDispatcher->expects($this->exactly(4))->method('dispatch')
            ->with(Events::PACKAGE_CREATE, $this->isInstanceOf('\Terramar\Packages\Event\PackageEvent'));
            
        $results = $this->sut->synchronizePackages($remote);
        $this->assertSame($packages, $results);
    }
}