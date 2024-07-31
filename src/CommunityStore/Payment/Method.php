<?php

namespace Concrete\Package\CommunityStore\Src\CommunityStore\Payment;

use Concrete\Core\User\User;
use Concrete\Core\View\View;
use Doctrine\ORM\Mapping as ORM;
use Concrete\Core\Controller\Controller;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\Support\Facade\DatabaseORM as dbORM;
use ReflectionException;

/**
 * @ORM\Entity
 * @ORM\Table(name="CommunityStorePaymentMethods")
 */
class Method extends Controller
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    protected $pmID;

    /** @ORM\Column(type="text") */
    protected $pmHandle;

    /** @ORM\Column(type="text") */
    protected $pmName;

    /** @ORM\Column(type="text", nullable=true) */
    protected $pmDisplayName;

    /** @ORM\Column(type="text", nullable=true) */
    protected $pmButtonLabel;

    /** @ORM\Column(type="string",nullable=true) */
    protected $pmUserGroups;

    /** @ORM\Column(type="string",nullable=true) */
    protected $pmExcludedUserGroups;

    /** @ORM\Column(type="boolean") */
    protected $pmEnabled;

    /** @ORM\Column(type="integer", nullable=true) */
    protected $pmSortOrder;

    /**
     * @ORM\Column(type="integer")
     */
    protected $pkgID;

    private $methodController;

    public function getID()
    {
        return $this->pmID;
    }

    public function getHandle()
    {
        return $this->pmHandle;
    }

    public function setHandle($handle)
    {
        $this->pmHandle = $handle;
    }

    public function getName()
    {
        return $this->pmName;
    }

    public function setName($name)
    {
        return $this->pmName = $name;
    }

    public function getButtonLabel()
    {
        return $this->pmButtonLabel;
    }

    public function setButtonLabel($pmButtonLabel)
    {
        $this->pmButtonLabel = $pmButtonLabel;
    }

    public function getPackageID()
    {
        return $this->pkgID;
    }

    public function setPackageID($pkgID)
    {
        $this->pkgID = $pkgID;
    }

    public function getSortOrder()
    {
        return $this->pmSortOrder;
    }

    public function setSortOrder($order)
    {
        $this->pmSortOrder = $order ? $order : 0;
    }

    public function getDisplayName()
    {
        if ("" == $this->pmDisplayName) {
            return $this->pmName;
        } else {
            return $this->pmDisplayName;
        }
    }

    public function setDisplayName($name)
    {
        $this->pmDisplayName = $name;
    }

    public function setEnabled($status)
    {
        $this->pmEnabled = (bool) $status;
    }

    public function isEnabled()
    {
        return $this->pmEnabled;
    }

    public function getUserGroups()
    {
        return $this->pmUserGroups ? explode(',', $this->pmUserGroups) : [];
    }

    public function setUserGroups($userGroups)
    {
        if (is_array($userGroups)) {
            $this->pmUserGroups = implode(',', $userGroups);
        } else {
            $this->pmUserGroups = '';
        }
    }

    public function getExcludedUserGroups()
    {
        return $this->pmExcludedUserGroups ? explode(',', $this->pmExcludedUserGroups) : [];
    }

    public function setExcludedUserGroups($userGroups)
    {
        if (is_array($userGroups)) {
            $this->pmExcludedUserGroups = implode(',', $userGroups);
        } else {
            $this->pmExcludedUserGroups = '';
        }
    }

    public static function getByID($pmID)
    {
        $em = dbORM::entityManager();
        $method = $em->find(__CLASS__, $pmID);

        if ($method) {
            $method->setMethodController();
        }

        return ($method instanceof self) ? $method : false;
    }

    public static function getByHandle($pmHandle)
    {
        $em = dbORM::entityManager();
        $method = $em->getRepository(__CLASS__)->findOneBy(['pmHandle' => $pmHandle]);

        if ($method) {
            $method->setMethodController();
        }

        return ($method instanceof self) ? $method : false;
    }

    public function getMethodDirectory()
    {
        if ($this->pkgID > 0) {
            $pkg = Application::getFacadeApplication()->make('Concrete\Core\Package\PackageService')->getByID($this->pkgID);
            $dir = $pkg->getPackagePath() . "/src/CommunityStore/Payment/Methods/" . $this->pmHandle . "/";
        }

        return $dir;
    }

    /**
     * @return bool returns false in case the package/method controller doesn't exist (anymore), true otherwise
     */
    protected function setMethodController()
    {
        $app = Application::getFacadeApplication();

        $th = $app->make("helper/text");
        $pkg = $app->make('Concrete\Core\Package\PackageService')->getByID($this->pkgID);
        if ($pkg === null) {
            return false;
        }
        $namespace = "Concrete\\Package\\" . $th->camelcase($pkg->getPackageHandle()) . "\\Src\\CommunityStore\\Payment\\Methods\\" . $th->camelcase($this->pmHandle);
        $className = $th->camelcase($this->pmHandle) . "PaymentMethod";
        $fullyQualifiedClassName = $namespace . '\\' . $className;
        if (!class_exists($fullyQualifiedClassName)) {
            throw new ReflectionException(sprintf("The payment method controller at '%s' for handle %s is missing. Are you sure the namespace is correct", $fullyQualifiedClassName));
        }
        $this->methodController = $app->make($fullyQualifiedClassName);

        return true;
    }

    public function getMethodController()
    {
        return $this->methodController;
    }

    /*
     * @param string $pmHandle
     * @param string $pmName
     * @ORM\pkg Package Object
     * @param string $pmDisplayName
     * @param bool $enabled
     */
    public static function add($pmHandle, $pmName, $pkg = null, $pmButtonLabel = '', $enabled = false)
    {
        $pm = self::getByHandle($pmHandle);
        if (!($pm instanceof self)) {
            $paymentMethod = new self();
            $paymentMethod->setHandle($pmHandle);
            $paymentMethod->setName($pmName);
            $paymentMethod->setPackageID($pkg->getPackageID());
            $paymentMethod->setDisplayName($pmName);
            $paymentMethod->setButtonLabel($pmButtonLabel);
            $paymentMethod->setEnabled($enabled);
            $paymentMethod->save();
        }
    }

    public static function getMethods($enabled = false)
    {
        $em = dbORM::entityManager();
        if ($enabled) {
            $methods = $em->getRepository(__CLASS__)->findBy(['pmEnabled' => 1], ['pmSortOrder' => 'ASC']);
        } else {
            $methods = $em->getRepository(__CLASS__)->findBy([], ['pmSortOrder' => 'ASC']);
        }
        $goodMethods = [];
        foreach ($methods as $method) {
            // TODO: Now that setMethodController() may throw an Exception, what behavior this should have ?
            $method->setMethodController();
            $goodMethods[] = $method;
        }

        return $goodMethods;
    }

    public static function getEnabledMethods()
    {
        return self::getMethods(true);
    }

    public static function getAvailableMethods($total)
    {
        $enabledMethods = self::getMethods(true);

        $availableMethods = [];

        $u = app(User::class);
        $userGroups = $u->getUserGroups();
        if ($u->isSuperUser()) {
            $userGroups[ADMIN_GROUP_ID] = ADMIN_GROUP_ID;
        }

        foreach ($enabledMethods as $em) {
            $includedGroups = $em->getUserGroups();
            $excludedGroups = $em->getExcludedUserGroups();

            if ($includedGroups !== [] && array_intersect($includedGroups, $userGroups) === []) {
                continue;
            }

            if ($excludedGroups !== [] && array_intersect($excludedGroups, $userGroups) !== []) {
                continue;
            }

            $emmc = $em->getMethodController();

            if ($total >= $emmc->getPaymentMinimum() && $total <= $emmc->getPaymentMaximum()) {
                $availableMethods[] = $em;
            }
        }

        $event = new PaymentEvent('add');
        $event->setMethods($availableMethods);

        \Events::dispatch(PaymentEvent::PAYMENT_ON_AVAILABLE_METHODS_GET, $event);

        $changed = $event->getChanged();
        if ($changed) {
            $availableMethods = $event->getMethods();
        }

        return $availableMethods;
    }


    public function renderCheckoutForm()
    {
        $class = $this->getMethodController();
        $class->checkoutForm();
        $pkg = Application::getFacadeApplication()->make('Concrete\Core\Package\PackageService')->getByID($this->pkgID);
        View::element($this->pmHandle . '/checkout_form', ['vars' => $class->getSets()], $pkg->getPackageHandle());
    }

    public function renderDashboardForm()
    {
        $controller = $this->getMethodController();
        $controller->dashboardForm();
        $pkg = Application::getFacadeApplication()->make('Concrete\Core\Package\PackageService')->getByID($this->pkgID);
        View::element($this->pmHandle . '/dashboard_form', ['vars' => $controller->getSets()], $pkg->getPackageHandle());
    }

    public function renderRedirectForm()
    {
        $controller = $this->getMethodController();
        $controller->redirectForm();
        $pkg = Application::getFacadeApplication()->make('Concrete\Core\Package\PackageService')->getByID($this->pkgID);
        View::element($this->pmHandle . '/redirect_form', ['vars' => $controller->getSets()], $pkg->getPackageHandle());
    }

    public function submitPayment()
    {
        //load controller
        $class = $this->getMethodController();

        return $class->submitPayment();
    }

    public function getPaymentMinimum()
    {
        return 0;
    }

    public function getPaymentMaximum()
    {
        return 1000000000; // raises pinky
    }

    public function save(array $data = [])
    {
        $em = dbORM::entityManager();
        $em->persist($this);
        $em->flush();
    }

    public function delete()
    {
        $this->remove();
    }

    public function remove()
    {
        $em = dbORM::entityManager();
        $em->remove($this);
        $em->flush();
    }

    public function isExternal()
    {
        return false;
    }

    /**
     * For external payment methods only.
     *
     * If the external URL should be invoked with a POST (whose body is specified in the redirectForm() method and redirect_form element), return false.
     * If the external URL should be invoked with GET, return true.
     *
     * @return bool
     */
    public function isExternalActionGET()
    {
        return false;
    }

    public function markPaid()
    {
        return true;
    }

    public function sendReceipt()
    {
        return true;
    }

    // method stub
    public function redirectForm()
    {
    }

    // method stub
    public function checkoutForm()
    {
    }

    // method stub
    public function getPaymentInstructions()
    {
        return '';
    }
}
