<?php

namespace TheFox\Test;

use PHPUnit\Framework\TestCase;
use Zend\Mail\Message;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use TheFox\Imap\Server;
use TheFox\Imap\Client;
use TheFox\Imap\MessageDatabase;
use TheFox\Imap\Event;
use TheFox\Imap\Storage\DirectoryStorage;
use TheFox\Imap\Storage\TestStorage;
use TheFox\Network\Socket;

class ServerTest extends TestCase
{
    public function testClientNew()
    {
        $socket = new Socket();

        $server = new Server();

        $client = $server->newClient($socket);
        $this->assertTrue($client instanceof Client);
    }

    public function testClientGetByHandle()
    {
        $socket = new Socket();
        $socket->bind('127.0.0.1', 22143);
        $socket->listen();
        $handle1 = $socket->getHandle();

        $server = new Server();
        
        $client1 = $server->newClient($socket);
        $client2 = $server->getClientByHandle($handle1);
        $this->assertEquals($client1, $client2);

        $server->shutdown();
    }

    public function testClientRemove()
    {
        $socket = new Socket();
        $socket->bind('127.0.0.1', 22143);
        $socket->listen();

        $server = new Server();

        $client = $server->newClient($socket);
        $server->removeClient($client);

        $this->assertTrue($client->getStatus('hasShutdown'));

        $server->shutdown();
    }

    public function testGetDefaultStorage()
    {
        $server = new Server();
        $storage1 = $server->getDefaultStorage();

        $this->assertTrue($storage1 instanceof DirectoryStorage);

        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testAddStorage()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true) . '/';
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new DirectoryStorage();
        $storage2->setPath($path2);
        $server->addStorage($storage2);

        $this->assertFileExists($path1);
        $this->assertFileExists($path2);

        $this->assertEquals($storage1, $server->getDefaultStorage());

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
        $filesystem->remove($storage2->getPath());
    }

    public function testAddFolder()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        #$path2 = './tmp/test_data/test_mailbox_'.date('Ymd_His').'_'.uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        #$storage2 = new TestStorage();
        #$storage2->setPath($path2);
        #$server->addStorage($storage2);

        $server->addFolder('test_dir1');
        $this->assertFileExists($path1 . '/test_dir1');
        #$this->assertFileExists($path2.'/test_dir1');

        $server->addFolder('test_dir2');
        $this->assertFileExists($path1 . '/test_dir2');
        #$this->assertFileExists($path2.'/test_dir2');

        $server->addFolder('test_dir2.test_dir3');
        $this->assertFileExists($path1 . '/test_dir2/test_dir3');
        #$this->assertFileExists($path2.'/test_dir2_test_dir3');

        $server->addFolder('test_dir4.test_dir5');
        $this->assertFileExists($path1 . '/test_dir4/test_dir5');
        #$this->assertFileExists($path2.'/test_dir4_test_dir5');

        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testGetFolders1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $storage2->setType('temp');
        $server->addStorage($storage2);

        $server->addFolder('test_dir1');
        $server->addFolder('test_dir2');
        $server->addFolder('test_dir2.test_dir3');


        $folders = $server->getFolders('', 'INBOX');
        $this->assertEquals(3, count($folders));
        $this->assertEquals('test_dir1', $folders[0]);
        $this->assertEquals('test_dir2', $folders[1]);
        $this->assertEquals('test_dir2.test_dir3', $folders[2]);

        $folders = $server->getFolders('', 'test_dir1');
        $this->assertEquals(1, count($folders));
        $this->assertEquals('test_dir1', $folders[0]);

        $folders = $server->getFolders('', 'test_dir2');
        $this->assertEquals(1, count($folders));
        $this->assertEquals('test_dir2', $folders[0]);

        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
        $filesystem->remove($storage2->getPath());
    }

    public function testGetFolders2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $storage2->setType('temp');
        $server->addStorage($storage2);

        $server->addFolder('Drafts');
        $server->addFolder('Trash');


        $folders = $server->getFolders('', '*');
        $this->assertEquals(2, count($folders));
        $this->assertEquals('Drafts', $folders[0]);
        $this->assertEquals('Trash', $folders[1]);

        $folders = $server->getFolders('', 'INBOX');
        $this->assertEquals(2, count($folders));
        $this->assertEquals('Drafts', $folders[0]);
        $this->assertEquals('Trash', $folders[1]);

        $folders = $server->getFolders('INBOX', '*');
        $this->assertEquals(2, count($folders));
        $this->assertEquals('Drafts', $folders[0]);
        $this->assertEquals('Trash', $folders[1]);

        $folders = $server->getFolders('', 'Drafts');
        $this->assertEquals(1, count($folders));
        $this->assertEquals('Drafts', $folders[0]);

        $folders = $server->getFolders('', 'Trash');
        $this->assertEquals(1, count($folders));
        $this->assertEquals('Trash', $folders[0]);

        $folders = $server->getFolders('Trash', '*');
        $this->assertEquals(0, count($folders));

        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
        $filesystem->remove($storage2->getPath());
    }

    public function testGetNextMsgId()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $this->assertEquals(100005, $server->getNextMsgId());

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testGetMsgSeqById()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);
        
        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(1, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(2, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(3, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(4, $server->getMsgSeqById($msgId));

        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testGetMsgIdBySeq()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals($msgId, $server->getMsgIdBySeq(1, 'INBOX'));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals($msgId, $server->getMsgIdBySeq(2, 'INBOX'));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals($msgId, $server->getMsgIdBySeq(3, 'INBOX'));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals($msgId, $server->getMsgIdBySeq(4, 'INBOX'));


        $server->addFolder('test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 9');
        $message->setBody('my_body');
        $msgId = $server->addMail($message, 'test_dir1');
        $this->assertEquals($msgId, $server->getMsgIdBySeq(1, 'test_dir1'));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 10');
        $message->setBody('my_body');
        $msgId = $server->addMail($message, 'test_dir1');
        $this->assertEquals($msgId, $server->getMsgIdBySeq(2, 'test_dir1'));


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testAddMail()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $server->addStorage($storage2);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(100001, $msgId);
        $this->assertEquals(1, $server->getMsgSeqById($msgId));


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(100002, $msgId);
        $this->assertEquals(2, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(100003, $msgId);
        $this->assertEquals(3, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(100004, $msgId);
        $this->assertEquals(4, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(100005, $msgId);
        $this->assertEquals(5, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);
        $this->assertEquals(100006, $msgId);
        $this->assertEquals(6, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 7');
        $message->setBody('my_body');
        $msgId = $server->addMail($message, '', [], false);
        $this->assertEquals(100007, $msgId);
        $this->assertEquals(7, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 8');
        $message->setBody('my_body');
        $msgId = $server->addMail($message, '', [], false);
        $this->assertEquals(100008, $msgId);
        $this->assertEquals(8, $server->getMsgSeqById($msgId));

        $finder = new Finder();
        $files = $finder->in($path1)->files();
        $this->assertEquals(8, count($files));


        $server->addFolder('test_dir1');

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 9');
        $message->setBody('my_body');
        $msgId = $server->addMail($message, 'test_dir1');
        $this->assertEquals(100009, $msgId);
        $this->assertEquals(1, $server->getMsgSeqById($msgId));

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 10');
        $message->setBody('my_body');
        $msgId = $server->addMail($message, 'test_dir1');
        $this->assertEquals(100010, $msgId);
        $this->assertEquals(2, $server->getMsgSeqById($msgId));


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testRemoveMail1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $server->addStorage($storage2);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);


        $finder = new Finder();
        $files = $finder->in($path1)->files()->depth(0)->name('*.eml');
        $this->assertEquals(6, count($files));

        $server->removeMailById(100002);

        $finder = new Finder();
        $files = $finder->in($path1)->files()->depth(0)->name('*.eml');
        $this->assertEquals(5, count($files));

        $this->assertEquals(4, $server->getMsgSeqById(100005));
        $this->assertEquals(5, $server->getMsgSeqById(100006));


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testRemoveMail2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $server->addStorage($storage2);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);
        $msgId = $tmpId;

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);


        $finder = new Finder();
        $files = $finder->in($path1)->files()->depth(0)->name('*.eml');
        $this->assertEquals(6, count($files));

        $server->removeMailBySeq(4, 'INBOX');

        $finder = new Finder();
        $files = $finder->in($path1)->files()->depth(0)->name('*.eml');
        $this->assertEquals(5, count($files));

        $this->assertEquals(null, $server->getMsgSeqById(100004));
        $this->assertEquals(4, $server->getMsgSeqById(100005));
        $this->assertEquals(5, $server->getMsgSeqById(100006));


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testCopyMail1()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $server->addStorage($storage2);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);


        $finder = new Finder();
        $files = $finder->in($path1)->files()->depth(0)->name('*.eml');
        $this->assertEquals(6, count($files));


        $server->addFolder('test_dir1');

        $server->copyMailById(100002, 'test_dir1');
        $server->copyMailById(100004, 'test_dir1');

        $this->assertEquals(1, $server->getMsgSeqById(100007));
        $this->assertEquals(2, $server->getMsgSeqById(100008));


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testCopyMail2()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $server->addStorage($storage2);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 2');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 3');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 4');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 5');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);

        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 6');
        $message->setBody('my_body');
        $tmpId = $server->addMail($message);


        $finder = new Finder();
        $files = $finder->in($path1)->files()->depth(0)->name('*.eml');
        $this->assertEquals(6, count($files));

        $server->addFolder('test_dir1');

        $server->copyMailBySequenceNum(2, 'INBOX', 'test_dir1');
        $server->copyMailBySequenceNum(4, 'INBOX', 'test_dir1');

        $this->assertEquals(1, $server->getMsgSeqById(100007));
        $this->assertEquals(2, $server->getMsgSeqById(100008));


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testGetMail()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $msgId = $server->addMail($message);

        $this->assertEquals(100001, $msgId);

        $message = $server->getMailById($msgId);
        $this->assertTrue($message instanceof Message);
        $this->assertEquals('my_subject 1', $message->getSubject());
        $this->assertEquals('my_body', $message->getBody());


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    /**
     * @return int
     */
    public function functionForTestEvent(): int
    {
        return 18;
    }

    public function testEvent()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        
        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);


        $testData = 21;
        $phpunit = $this;
        $event1 = new Event(Event::TRIGGER_MAIL_ADD_PRE, null, function ($event) use ($phpunit, &$testData) {
            $phpunit->assertEquals(21, $testData);

            $testData = 24;

            return 42;
        });
        $server->addEvent($event1);

        $event2 = new Event(Event::TRIGGER_MAIL_ADD_PRE, $this, 'functionForTestEvent');
        $server->addEvent($event2);

        $event3 = new Event(Event::TRIGGER_MAIL_ADD, null, function ($event, $mail) use ($phpunit) {
            $phpunit->assertTrue(is_object($mail));
            $phpunit->assertEquals('my_subject 1', $mail->getSubject());
        });
        $server->addEvent($event3);

        $event4 = new Event(Event::TRIGGER_MAIL_ADD_POST, null, function ($event, $msgId) use ($phpunit) {
            $phpunit->assertEquals(100001, $msgId);
        });
        $server->addEvent($event4);


        $message = new Message();
        $message->addFrom('dev1@fox21.at');
        $message->addTo('dev2@fox21.at');
        $message->setSubject('my_subject 1');
        $message->setBody('my_body');
        $server->addMail($message);

        $this->assertEquals(24, $testData);
        $this->assertEquals(42, $event1->getReturnValue());
        $this->assertEquals(18, $event2->getReturnValue());
        $this->assertEquals(null, $event3->getReturnValue());
        $this->assertEquals(null, $event4->getReturnValue());


        $server->shutdown();
        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
    }

    public function testShutdownStorages()
    {
        $path1 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path2 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);
        $path3 = './tmp/test_data/test_mailbox_' . date('Ymd_His') . '_' . uniqid('', true);

        $server = new Server();

        $storage1 = new DirectoryStorage();
        $storage1->setPath($path1);
        $server->addStorage($storage1);

        $storage2db = new MessageDatabase();
        $storage2 = new TestStorage();
        $storage2->setPath($path2);
        $storage2->setType('temp');
        $storage2->setDb($storage2db);
        $storage2->setDbPath($path2 . '/msgdb.yml');
        $server->addStorage($storage2);

        $storage3 = new TestStorage();
        $storage3->setPath($path3);
        $server->addStorage($storage3);

        $this->assertEquals('normal', $storage1->getType());
        $this->assertEquals('temp', $storage2->getType());
        $this->assertEquals('normal', $storage3->getType());

        $this->assertTrue($server->getDefaultStorage() instanceof DirectoryStorage);

        $server->addFolder('test_dir1');
        #$server->shutdownStorages();
        $server->shutdown();

        $this->assertTrue(file_exists($path1));
        $this->assertFalse(file_exists($path2));
        $this->assertTrue(file_exists($path3));

        $filesystem = new Filesystem();
        $filesystem->remove($storage1->getPath());
        $filesystem->remove($storage2->getPath());
        $filesystem->remove($storage3->getPath());
    }
}
