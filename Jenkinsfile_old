pipeline {
    agent any

    environment {
        IMAGE_TAG = "${BUILD_NUMBER}"
        DOCKER_IMAGE = "mdnaiim/agrobd-app:${BUILD_NUMBER}"
    }

    // pull/copy from git repo
    stages {
        stage('Checkout App Repo') {
            steps {
                git branch: 'main',
                    credentialsId: 'jenkins-github-https-cred',
                    url: 'https://github.com/abunaiim25/agrobd-application.git'
            }
        }

        stage('Build Docker Image') {
            steps {
                script {
                    echo "🛠 Building Docker Image..."
                    sh "docker build -t ${env.DOCKER_IMAGE} ."
                }
            }
        }

        stage('Push Docker Image to DockerHub') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'dockerhub-cred',
                                  usernameVariable: 'DOCKERHUB_USER',
                                  passwordVariable: 'DOCKERHUB_PASS')]) {
                    script {
                        echo "🔐 Logging in to DockerHub..."
                        sh """
                        echo "$DOCKERHUB_PASS" | docker login -u "$DOCKERHUB_USER" --password-stdin
                        docker push ${env.DOCKER_IMAGE}
                        docker logout
                        """
                    }
                }
            }
        }

        // pull/copy from git repo
        stage('Checkout K8s Manifest Repo') {
            steps {
                git branch: 'main',
                    credentialsId: 'jenkins-github-https-cred',
                    url: 'https://github.com/abunaiim25/AgroBd-DEPLOYMENT.git'
            }
        }

        stage('Update K8s Manifest & Push') {
            steps {
                withCredentials([usernamePassword(credentialsId: 'jenkins-github-https-cred')]) {
                    script {
                        echo "📝 Updating deployment.yaml with new image tag..."
                        sh """
                        sed -i "s#mdnaiim/agrobd-app:.*#${env.DOCKER_IMAGE}#g" agrobd-app/deployment.yaml

                        git config user.email "jenkins@local"
                        git config user.name "Jenkins Pipeline"
                        git add agrobd-app/deployment.yaml
                        git commit -m "Updated deployment.yaml with image tag ${env.IMAGE_TAG}" || echo "No changes to commit"
                        git push origin main
                        """
                    }
                }
            }
        }
    }
}
