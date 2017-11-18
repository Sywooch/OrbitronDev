<?php

namespace Controller;

use App\Account\AccountHelper;
use App\Account\Entity\User;
use App\Core\Core;
use App\Core\Form\ContactType;
use App\Core\Form\SearchType;
use Doctrine\ORM\Tools\SchemaTool;
use Swift_Message;

class DefaultController extends \Controller
{
    public function redirToIndexAction()
    {
        return $this->redirectToRoute('homepage');
    }

    public function indexAction()
    {
        if (is_null(AccountHelper::updateSession())) {
            return $this->redirectToRoute('app_account_logout');
        }
        /** @var \App\Account\Entity\User $currentUser */
        $currentUser = $this->getEntityManager()->find(User::class, USER_ID);

        return $this->render('default/index.html.twig', array(
            'current_user' => $currentUser,
        ));
    }

    public function aboutAction()
    {
        return $this->render('default/about.html.twig');
    }

    public function aboutTeamAction()
    {
        return $this->render('default/about-team.html.twig');
    }

    public function contactAction()
    {
        $contactForm = $this->createForm(ContactType::class);

        $request = $this->getRequest();
        $contactForm->handleRequest($request);
        if ($contactForm->isSubmitted() && $contactForm->isValid()) {
            $formData = $contactForm->getData();

            if ($contactForm->get('send_to_own')->getData()) {
                $message = (new Swift_Message())
                    ->setSubject(trim($formData['subject']))
                    ->setFrom(array(trim($formData['email']) => trim($formData['name'])))
                    ->setTo(array('info@orbitrondev.org'))
                    ->setCc(array(trim($formData['email']) => trim($formData['name'])))
                    ->setBody($this->renderView('default/mail/contact.html.twig', array(
                        'ip'      => $request->getClientIp(),
                        'name'    => $formData['name'],
                        'message' => $formData['message'],
                    )), 'text/html');
            } else {
                $message = (new Swift_Message())
                    ->setSubject(trim($formData['subject']))
                    ->setFrom(array(trim($formData['email']) => trim($formData['name'])))
                    ->setTo(array('info@orbitrondev.org'))
                    ->setBody($this->renderView('default/mail/contact.html.twig', array(
                        'ip'      => $request->getClientIp(),
                        'name'    => $formData['name'],
                        'message' => $formData['message'],
                    )), 'text/html');
            }
            /** @var \Swift_Mailer $mailer */
            $mailer = $this->get('mailer');
            $mailSent = $mailer->send($message);

            $this->addFlash('success', 'Your email has been sent! Thanks!');

            if($mailSent) {
                return $this->redirectToRoute('app_default_contact', array('sent' => 1));
            } else {
                return $this->redirectToRoute('app_default_contact', array('sent' => 0));
            }
        }

        return $this->render('default/contact.html.twig', array(
            'contact_form' => $contactForm->createView(),
        ));
    }

    public function termsAction()
    {
        return $this->render('default/terms.html.twig');
    }

    public function privacyAction()
    {
        return $this->render('default/privacy.html.twig');
    }

    public function faqAction()
    {
        return $this->render('default/faq.html.twig');
    }

    public function searchAction()
    {
        $response = $this->searchHandler();
        if ($response !== null) {
            return $response;
        }

        $request = $this->getRequest();

        return $this->render('default/search.html.twig', array(
            'search'   => $request->query->get('q'),
        ));
    }

    private function searchHandler()
    {
        $searchForm = $this->createForm(SearchType::class);

        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $searchForm->handleRequest($request);

            if ($searchForm->isSubmitted() && $searchForm->isValid()) {
                return $this->redirect($this->generateUrl('app_default_search', array('q' => $searchForm->get('search')->getData())));
            }
        }

        return null;
    }

    public function oneTimeSetupAction()
    {
        $em = $this->getEntityManager();
        $classes = array(
            $em->getClassMetadata('App\Account\Entity\OAuthAccessToken'),
            $em->getClassMetadata('App\Account\Entity\OAuthAuthorizationCode'),
            $em->getClassMetadata('App\Account\Entity\OAuthClient'),
            $em->getClassMetadata('App\Account\Entity\OAuthRefreshToken'),
            $em->getClassMetadata('App\Account\Entity\OAuthScope'),
            $em->getClassMetadata('App\Account\Entity\SubscriptionType'),
            $em->getClassMetadata('App\Account\Entity\User'),
            $em->getClassMetadata('App\Account\Entity\UserAddress'),
            $em->getClassMetadata('App\Account\Entity\UserPaymentMethods'),
            $em->getClassMetadata('App\Account\Entity\UserProfiles'),
            $em->getClassMetadata('App\Account\Entity\UserSubscription'),
            $em->getClassMetadata('App\Blog\Entity\Blog'),
            $em->getClassMetadata('App\Blog\Entity\Category'),
            $em->getClassMetadata('App\Blog\Entity\Comment'),
            $em->getClassMetadata('App\Blog\Entity\Post'),
            $em->getClassMetadata('App\Blog\Entity\Tag'),
            $em->getClassMetadata('App\Core\Entity\CronJob'),
            $em->getClassMetadata('App\Core\Entity\Token'),
        );
        if ($this->getRequest()->query->get('key') == $this->get('config')['parameters']['setup_key']) {
            if ($this->getRequest()->query->get('action') == 'drop-schema') {
                $tool = new SchemaTool($em);
                $tool->dropSchema($classes);
                return 'Database schema dropped';
            }
            if ($this->getRequest()->query->get('action') == 'create-schema') {
                $tool = new SchemaTool($em);
                $tool->createSchema($classes);
                return 'Database schema created';
            }
            if ($this->getRequest()->query->get('action') == 'update-schema') {
                $tool = new SchemaTool($em);
                $tool->updateSchema($classes);
                return 'Database schema updated';
            }
            if ($this->getRequest()->query->get('action') == 'add-default-entries') {
                $text = '';
                Core::addDefaultCronJobs();
                $text .= 'Default cron jobs added<br />';
                return $text;
            }
        }
        return 'No setup key given, or key not correct.';
    }
}
